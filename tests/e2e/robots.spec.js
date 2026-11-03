// SEO-01: robots.txt is a virtual file that WordPress builds in index.php (from blog_public); there is
// no file on disk. Nginx must hand the request to WordPress instead of answering its own 404, which
// is what the VPS vhost did until its static-asset regex stopped catching it (infra/server/nginx).
// The routing rules themselves are checked against the shipped vhost in tests/infra/nginx-staging-auth.sh.
import { test, expect } from '@playwright/test';

test( '/robots.txt is answered by WordPress', async ( { request } ) => {
	const response = await request.get( '/robots.txt', { maxRedirects: 0 } );
	expect( response.status() ).toBe( 200 );
	expect( response.headers()[ 'content-type' ] ).toMatch( /^text\/plain/ );
	const body = await response.text();
	expect( body ).toMatch( /^User-agent: \S+/m );
	// Not the coming-soon page or any other HTML: WordPress's robots output, before the theme runs.
	expect( body ).not.toMatch( /<html/i );
} );
