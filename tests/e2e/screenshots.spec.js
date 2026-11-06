// Takes the screenshots for readme.txt and .wordpress-org/.
const { test } = require( '@playwright/test' );
const { prepareSite, shot } = require( './utils/media' );
const { wp } = require( './utils/wp-cli' );

test.describe( 'Screenshots', () => {
	test.beforeEach( () => {
		prepareSite( 'Acme Studio' );
	} );

	test( 'captures plugin screenshots', async ( { page, browser } ) => {
		// Setup mock lockouts and custom slug.
		wp( 'eval', `
			update_option( 'pratiks_login_shield_settings', array(
				'login_slug'       => 'secret-portal',
				'max_attempts'     => 4,
				'lockout_duration' => 20,
				'disable_xmlrpc'   => true,
				'rescue_key'       => '48a9f2bc71e04b2c89f5d1e8c7b6a504938271605e4d3c2b',
			) );
			update_option( 'pratiks_login_shield_lockouts', array(
				'198.51.100.42' => array(
					'locked_until' => time() + 1080,
					'attempts'     => 4,
					'last_user'    => 'admin',
					'created_at'   => time() - 120,
				),
				'203.0.113.88' => array(
					'locked_until' => time() + 720,
					'attempts'     => 5,
					'last_user'    => 'root',
					'created_at'   => time() - 480,
				),
			) );
			flush_rewrite_rules();
		` );

		// 1. Settings screen.
		await page.setViewportSize( { width: 1280, height: 800 } );
		await page.goto( '/wp-admin/options-general.php?page=pratiks-login-shield' );
		await page.waitForLoadState( 'networkidle' );
		await shot( page, 1 );

		// 2. Custom Login URL screen with remaining attempts notice.
		const incognito = await browser.newContext( {
			storageState: { cookies: [], origins: [] },
			viewport: { width: 1280, height: 800 },
		} );
		const anonPage = await incognito.newPage();

		await anonPage.goto( '/secret-portal/' );
		await anonPage.locator( '#user_login' ).fill( 'admin' );
		await anonPage.locator( '#user_pass' ).fill( 'wrongpassword' );
		await anonPage.locator( '#wp-submit' ).click();
		await anonPage.waitForSelector( '#login_error' );
		await shot( anonPage, 2 );

		// 3. Lockout screen.
		// Trigger lockout on client IP.
		wp( 'eval', `
			\\PratikLondhe\\PratiksLoginShield\\Auth\\RateLimiter::lock_ip( '127.0.0.1', 'admin', 4 );
		` );
		await anonPage.goto( '/secret-portal/' );
		await anonPage.locator( '#user_login' ).fill( 'admin' );
		await anonPage.locator( '#user_pass' ).fill( 'wrongpassword' );
		await anonPage.locator( '#wp-submit' ).click();
		await anonPage.waitForSelector( '#login_error' );
		await shot( anonPage, 3 );

		await incognito.close();
		wp( 'pratiks-login-shield', 'reset' );
	} );
} );
