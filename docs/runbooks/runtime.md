# Runtime: the Compose stack (FND-02)

One stack for the sandbox, CI and laptops, pinned to the VPS minors: PHP 8.5-FPM (custom image), nginx 1.28, MariaDB 11.8, Valkey 9.0, Mailpit. The repository is bind-mounted into the containers; Composer runs on the host.

## Start, stop, reset

```bash
make up          # scripts/runtime-up.sh: build image, start, install WordPress, provision, seed (idempotent)
```

```bash
make down        # stop, keep the database
```

```bash
make destroy     # stop and delete the database volume (next `make up` reinstalls WordPress)
```

| What | Where |
|---|---|
| Store | http://localhost:8080 (change with `HTTP_PORT`) |
| Admin | http://localhost:8080/wp/wp-admin/ with `admin` / `admin` (`WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`) |
| Trapped mail | http://localhost:8025 (Mailpit; every `wp_mail()` lands here through PHP's `sendmail_path`) |
| WP-CLI | `make wp ARGS="option get blogname"` or `docker compose exec php wp ...` (`wp-cli.yml` points at `web/wp`) |
| Database | `docker compose exec db mariadb -ucigarholic -pcigarholic cigarholic`, or from the php container with `mariadb -h db` |
| Object cache | Redis Object Cache plugin against the `cache` service (`wp redis status`) |
| Logs | `make logs`; PHP errors on the php container's stderr; `WP_DEBUG_LOG` under `web/app/debug.log` in development |

`scripts/runtime-up.sh` options: `--no-build` (reuse the image), `--no-seed`, `--build-only` (used by `infra/cloud-setup.sh` to warm the image cache).

## What the script does

1. Starts `dockerd` if it is not running (sandbox), copies the sandbox proxy CA into `docker/ca/proxy.crt` (gitignored) when present.
2. Creates `.env` from `.env.example` with random salts.
3. `composer install` and `scripts/tools.sh` if `vendor/` or the phars are missing.
4. Builds `cigarholic/php:<version>` with `docker build --network host` and the CA as a build secret (Compose's bake builder refuses both without entitlements), pulls the other images, `docker compose up -d --wait`.
5. `wp core install`, activates WooCommerce first and then every plugin, activates the theme when present.
6. `wp cigarholic provision` and `wp cigarholic seed` when the commands exist.
7. Smoke check: `wp-login.php` must return 200.

## Sandbox specifics

- All HTTPS from the host and the containers goes through the agent proxy; the build and the php container receive `HTTPS_PROXY` and the CA. WordPress's own HTTP client uses its bundled CA bundle, so `wp_update_plugins()` warns about a "secure connection" in the sandbox; harmless, and `cigarholic-core` makes WordPress trust the system bundle in development.
- `docker compose build` would ask for `--allow=network.host` / `--allow=fs.read`; use `scripts/runtime-up.sh` instead.
- WP-CLI 2.12's own libraries emit PHP 8.5 deprecations; `docker/php/wp` lowers error reporting for the CLI process only, and `WP_DEBUG_DISPLAY` is off under WP-CLI in development. PHP-FPM keeps `E_ALL` so deprecations from WordPress, WooCommerce and plugins are visible (PHP 8.5 gate, ADR 0001).
- phpredis is downloaded from pecl.php.net with curl and compiled in the image because PEAR's HTTP/1.0 client receives `426 Upgrade Required`.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `Docker is not available` | `nohup dockerd >/tmp/dockerd.log 2>&1 &` then retry (`service docker start` does not work in the sandbox) |
| Image build fails downloading | check `curl -sS "$HTTPS_PROXY/__agentproxy/status"`; retry, the proxy times out under load |
| `wp-login.php returned 502` | `docker compose logs php web`; usually PHP-FPM still starting, re-run `make up` |
| Plugins fail to activate | WooCommerce must be active first; `make wp ARGS="plugin activate --all"` |
| Database volume from an older schema | `make destroy && make up` |
