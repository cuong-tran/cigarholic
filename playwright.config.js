// Playwright smoke suite (FND-12) against the Compose runtime (or staging with E2E_BASE_URL).
// Sandbox: PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers (preinstalled Chromium); CI installs Chromium.
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8080';

export default defineConfig( {
	testDir: './tests/e2e',
	timeout: 60_000,
	expect: { timeout: 10_000 },
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never' } ] ] : 'list',
	outputDir: 'test-results',
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
		// The Claude Code sandbox intercepts TLS with its own CA (CCR_AGENT_PROXY_ENABLED is set there).
		ignoreHTTPSErrors: !! ( process.env.E2E_IGNORE_HTTPS_ERRORS || process.env.CCR_AGENT_PROXY_ENABLED ),
		extraHTTPHeaders: process.env.E2E_BASIC_AUTH
			? { Authorization: 'Basic ' + Buffer.from( process.env.E2E_BASIC_AUTH ).toString( 'base64' ) }
			: {},
	},
	projects: [
		{ name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } },
		{ name: 'mobile', use: { ...devices[ 'Pixel 7' ] }, testMatch: /coming-soon|storefront/ },
	],
} );
