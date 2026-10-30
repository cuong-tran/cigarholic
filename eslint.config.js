// ESLint flat config. JavaScript lives only in the theme, the mu-plugin blocks and the Playwright tests.
import js from '@eslint/js';
import globals from 'globals';

export default [
	{
		ignores: [
			'.claude/**',
			'vendor/**',
			'node_modules/**',
			'tools/**',
			'web/wp/**',
			'web/app/plugins/**',
			'web/app/uploads/**',
			'web/app/mu-plugins/cigarholic-core/build/**',
			'playwright-report/**',
			'test-results/**',
		],
	},
	js.configs.recommended,
	{
		files: ['web/app/**/*.js'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: { ...globals.browser, wp: 'readonly', jQuery: 'readonly' },
		},
	},
	{
		files: ['tests/e2e/**/*.{js,ts}', '*.config.js', '*.config.ts'],
		languageOptions: {
			ecmaVersion: 2022,
			sourceType: 'module',
			globals: { ...globals.node },
		},
	},
];
