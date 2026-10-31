<?php
/**
 * Deployer 8 recipe for Cigarholic (OPS-04, OPS-05, OPS-06, OPS-08).
 *
 * Atomic releases under /var/www/<site>/{releases,shared,current}. Run from GitHub Actions
 * (deploy-staging.yml, deploy-production.yml) or from any machine that holds the SSH key:
 *
 *   DEPLOY_HOST=<ip> DEPLOY_USER=deploy php tools/deployer.phar --file=infra/deploy/deploy.php deploy staging --revision=<sha>
 *   DEPLOY_HOST=<ip> DEPLOY_USER=deploy php tools/deployer.phar --file=infra/deploy/deploy.php deploy production --tag=v1.2.3
 *   DEPLOY_HOST=<ip> DEPLOY_USER=deploy php tools/deployer.phar --file=infra/deploy/deploy.php rollback production
 *
 * Environment (names only; values come from GitHub secrets):
 *   DEPLOY_HOST            VPS address (required; the recipe refuses to run without it)
 *   DEPLOY_PORT            SSH port (default 22)
 *   DEPLOY_USER            SSH user (default deploy). With root, every command runs as deploy via sudo.
 *   DEPLOY_REPOSITORY      git URL to clone on the server (default: the public GitHub repository)
 *   DEPLOY_IDENTITY_FILE   SSH private key file (default: whatever ~/.ssh/config provides)
 *   HEALTH_BASIC_AUTH      user:password for the post-deploy health check on staging
 *   CLOUDFLARE_API_TOKEN, CLOUDFLARE_ZONE_ID_CIGARHOLIC   optional: purge the edge cache after deploy
 *
 * Both sites live on one VPS; the host alias (staging | production) selects the site.
 */

namespace Deployer;

use Deployer\Exception\ConfigurationException;

require 'recipe/common.php';

// ---------------------------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------------------------
set('application', 'cigarholic');
set('repository', getenv('DEPLOY_REPOSITORY') ?: 'https://github.com/cuong-tran/cigarholic.git');
set('keep_releases', 5);
set('default_timeout', 900);

set('shared_files', ['.env']);
set('shared_dirs', ['web/app/uploads', 'logs']);
set('writable_dirs', ['web/app/uploads', 'logs']);
set('writable_mode', 'acl');          // deploy and the site's PHP-FPM user both read/write
set('writable_use_sudo', false);

set('composer_options', '--no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader');
set('php_fpm_service', 'php8.5-fpm');
set('bin/wp', 'wp --path={{release_or_current_path}}/web/wp');

// ---------------------------------------------------------------------------------------------
// Hosts: one VPS, two sites. The alias is the site; hostname and user come from the environment.
// ---------------------------------------------------------------------------------------------
$deployHost = getenv('DEPLOY_HOST') ?: '';
$deployUser = getenv('DEPLOY_USER') ?: 'deploy';
$deployPort = (int) (getenv('DEPLOY_PORT') ?: 22);

$sites = [
    'staging'    => ['url' => 'https://staging.cigarholic.com', 'host' => 'staging.cigarholic.com'],
    'production' => ['url' => 'https://cigarholic.com',         'host' => 'cigarholic.com'],
];

foreach ($sites as $alias => $site) {
    $h = host($alias)
        ->setHostname($deployHost !== '' ? $deployHost : 'deploy-host-not-set.invalid')
        ->setRemoteUser($deployUser)
        ->setPort($deployPort)
        ->setDeployPath("/var/www/$alias")
        ->setLabels(['stage' => $alias])
        ->setSshArguments(['-o', 'StrictHostKeyChecking=yes', '-o', 'ServerAliveInterval=30'])
        ->set('site', $alias)
        ->set('site_url', $site['url'])
        ->set('site_host', $site['host'])
        ->set('http_user', "cigarholic-$alias")
        ->set('http_group', "cigarholic-$alias");

    if ($identity = getenv('DEPLOY_IDENTITY_FILE')) {
        $h->setIdentityFile($identity);
    }
    if ($deployUser === 'root') {
        // Until the owner switches SSH_USER to deploy: connect as root, act as deploy.
        $h->set('become', 'deploy');
    }
}

// ---------------------------------------------------------------------------------------------
// Guards
// ---------------------------------------------------------------------------------------------
desc('Refuses to run without an explicit host and a resolvable target');
task('cigarholic:guard', function () use ($deployHost) {
    if ($deployHost === '') {
        throw new ConfigurationException('DEPLOY_HOST is not set. Export it from the SSH_HOST secret; the recipe never hard-codes a host.');
    }
    if (!in_array(get('site'), ['staging', 'production'], true)) {
        throw new ConfigurationException('Select a site: dep deploy staging | dep deploy production');
    }
    if (get('site') === 'production') {
        $target = get('target');
        if (!preg_match('/^v\d+\.\d+\.\d+$/', $target)) {
            throw new ConfigurationException("Production deploys only from a release tag (--tag=vX.Y.Z); got \"$target\".");
        }
    }
    if (!test('[ -f {{deploy_path}}/shared/.env ]')) {
        throw new ConfigurationException(parse('{{deploy_path}}/shared/.env is missing: run infra-provision.yml with action site-{{site}} first.'));
    }
    info(parse('deploying {{target}} to {{site}} ({{site_url}}) as ' . run('id -un')));
});

// ---------------------------------------------------------------------------------------------
// Database snapshot before anything touches the schema (production only)
// ---------------------------------------------------------------------------------------------
desc('Dumps the database to shared/backups/pre-deploy-TIMESTAMP.sql.gz');
task('cigarholic:snapshot', function () {
    run('bash {{release_path}}/infra/backup/snapshot.sh {{site}}', timeout: 1800);
})->select('stage=production');

// ---------------------------------------------------------------------------------------------
// WordPress tasks in the new release before it goes live
// ---------------------------------------------------------------------------------------------
function wpInstalled(): bool
{
    return test('cd {{release_or_current_path}} && {{bin/wp}} core is-installed >/dev/null 2>&1');
}

desc('Runs wp core update-db, wp wc update, wp cigarholic provision and wp redis enable in the new release');
task('cigarholic:wp:prepare', function () {
    if (!wpInstalled()) {
        warning('WordPress is not installed in this database yet: skipping update-db, wc update, provision and redis enable. Install it with wp-cli.yml (wp core install ...), then deploy again.');
        return;
    }
    cd('{{release_path}}');
    run('{{bin/wp}} core update-db', timeout: 900);
    run('{{bin/wp}} wc update', timeout: 900);

    $provision = run('{{bin/wp}} cigarholic provision 2>&1 || true', timeout: 900);
    if (preg_match("/'cigarholic' is not a registered wp command|not a registered subcommand/i", $provision)) {
        warning('wp cigarholic provision is not available yet (cigarholic-core mu-plugin not deployed); skipped.');
    } elseif (preg_match('/^Error:/m', $provision)) {
        throw new \RuntimeException("wp cigarholic provision failed:\n$provision");
    }

    $redis = run('{{bin/wp}} redis enable 2>&1 || true');
    if (preg_match('/^Error:|not a registered/mi', $redis)) {
        warning("wp redis enable did not succeed (object cache stays off):\n$redis");
    }
});

desc('Flushes the WordPress object cache in the live release');
task('cigarholic:wp:after', function () {
    if (!wpInstalled()) {
        return;
    }
    cd('{{current_path}}');
    run('{{bin/wp}} cache flush 2>&1 || true');
});

// ---------------------------------------------------------------------------------------------
// Runtime: PHP-FPM reload (opcache), FastCGI cache purge, optional Cloudflare purge
// ---------------------------------------------------------------------------------------------
desc('Reloads PHP-FPM (clears opcache for the new release)');
task('cigarholic:php-fpm:reload', function () {
    run('sudo -n systemctl reload {{php_fpm_service}}');
});

desc('Empties the Nginx FastCGI cache of the site');
task('cigarholic:cache:purge', function () {
    run('sudo -n /usr/local/sbin/cigarholic-cache-purge {{site}}');
});

desc('Purges the Cloudflare cache when CLOUDFLARE_API_TOKEN and the zone id are set (ADR 0008: optional)');
task('cigarholic:cloudflare:purge', function () {
    if (get('site') !== 'production') {
        return;
    }
    if (!getenv('CLOUDFLARE_API_TOKEN') || !getenv('CLOUDFLARE_ZONE_ID_CIGARHOLIC')) {
        info('Cloudflare purge skipped: CLOUDFLARE_API_TOKEN or CLOUDFLARE_ZONE_ID_CIGARHOLIC not set');
        return;
    }
    runLocally('bash infra/cloudflare/purge.sh', timeout: 120);
});

// ---------------------------------------------------------------------------------------------
// Health check after the symlink switch; rollback on failure
// ---------------------------------------------------------------------------------------------
desc('Requests the home page through Nginx on the server and rolls back when it fails');
task('cigarholic:health', function () {
    $auth = getenv('HEALTH_BASIC_AUTH') ?: '';
    if (trim($auth, ':') === '') {
        $auth = ''; // both secrets empty: the workflow passes a bare ":"
    }
    $cmd = 'curl -sS -o /dev/null -w "%{http_code}" --max-time 30 --retry 2 --retry-delay 3 '
        . '--resolve {{site_host}}:443:127.0.0.1 -k'
        . ($auth !== '' ? ' -u "$HEALTH_BASIC_AUTH"' : '')
        . ' https://{{site_host}}/';
    $code = trim(run($cmd . ' || true', env: ['HEALTH_BASIC_AUTH' => $auth], secrets: $auth !== '' ? [$auth] : null));

    $ok = in_array($code, ['200', '301', '302', '303', '307', '308'], true);
    if (!$ok && $code === '401' && get('site') === 'staging' && $auth === '') {
        warning('health check got 401 from staging basic auth and no HEALTH_BASIC_AUTH was given; treating as alive');
        $ok = true;
    }
    if ($ok) {
        info("health check ok: HTTP $code from {{site_url}}");
        return;
    }
    warning("health check failed (HTTP $code); rolling back");
    invoke('rollback');
    run('sudo -n systemctl reload {{php_fpm_service}}');
    run('sudo -n /usr/local/sbin/cigarholic-cache-purge {{site}}');
    throw new \RuntimeException("Deploy rolled back: {{site_url}} answered HTTP $code after the release switch.");
});

// ---------------------------------------------------------------------------------------------
// The deploy
// ---------------------------------------------------------------------------------------------
desc('Deploys the site: prepare release, vendors, snapshot (production), WordPress updates, switch, health check');
task('deploy', [
    'cigarholic:guard',
    'deploy:prepare',           // info, setup, lock, release, update_code, env, shared, writable
    'deploy:vendors',           // composer install --no-dev --prefer-dist --no-interaction
    'cigarholic:snapshot',      // production only, before the schema changes
    'cigarholic:wp:prepare',    // wp core update-db, wp wc update, wp cigarholic provision, wp redis enable
    'deploy:symlink',           // atomic switch of current/
    'cigarholic:php-fpm:reload',
    'cigarholic:cache:purge',
    'cigarholic:wp:after',      // wp cache flush
    'cigarholic:health',        // rollback on failure
    'cigarholic:cloudflare:purge',
    'deploy:unlock',
    'deploy:cleanup',           // keep_releases = 5
    'deploy:success',
]);

// Unlock after a failure, unless the guard refused before any connection was made.
after('deploy:failed', function () use ($deployHost) {
    if ($deployHost !== '') {
        invoke('deploy:unlock');
    }
});

// `dep rollback <site>` is Deployer's own task; after it the runtime is refreshed too.
after('rollback', 'cigarholic:php-fpm:reload');
after('rollback', 'cigarholic:cache:purge');
