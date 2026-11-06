// Records the demo GIF for README.md.
const { test } = require( '@playwright/test' );
const { prepareSite, recordDemo } = require( './utils/media' );
const { wp } = require( './utils/wp-cli' );

test.describe( 'Demo', () => {
	test.beforeEach( () => {
		prepareSite( 'Acme Studio' );
		wp( 'pratiks-login-shield', 'reset' );
	} );

	test.afterEach( () => {
		wp( 'pratiks-login-shield', 'reset' );
	} );

	test( 'records Login Shield walkthrough', async ( { browser } ) => {
		await recordDemo( browser, async ( page, demo ) => {
			await page.goto( '/wp-admin/options-general.php?page=pratiks-login-shield' );
			await page.waitForLoadState( 'networkidle' );

			await demo.start();
			await demo.pause( 800 );

			// 1. Point to custom login slug and type slug.
			await demo.type( page.locator( '#login_slug' ), 'secure-portal' );
			await demo.pause( 600 );

			// 2. Enable XML-RPC protection.
			await demo.click( page.locator( '#disable_xmlrpc' ) );
			await demo.pause( 600 );

			// 3. Save settings.
			await demo.click( page.locator( '#submit_settings' ) );
			await page.waitForLoadState( 'networkidle' );
			await demo.pause( 1000 );

			// 4. Hover over Copy Rescue URL button.
			await demo.moveTo( page.locator( '#pratiks-copy-rescue-btn' ) );
			await demo.pause( 1200 );
		} );
	} );
} );
