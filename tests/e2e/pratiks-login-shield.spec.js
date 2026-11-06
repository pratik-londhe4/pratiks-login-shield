// End-to-end tests for Pratiks Login Shield.
const { test, expect } = require( '@playwright/test' );
const { wp } = require( './utils/wp-cli' );

test.describe( 'Login Shield', () => {
	test.beforeEach( () => {
		// Ensure clean state before each test.
		wp( 'pratiks-login-shield', 'reset' );
	} );

	test.afterEach( () => {
		wp( 'pratiks-login-shield', 'reset' );
	} );

	test( 'the plugin is active and settings page renders properly', async ( { page } ) => {
		await page.goto( '/wp-admin/options-general.php?page=pratiks-login-shield' );

		// Headings and UI elements.
		await expect( page.locator( 'h1' ) ).toHaveText( 'Login Shield' );
		await expect( page.locator( 'input#login_slug' ) ).toBeVisible();
		await expect( page.locator( 'input#max_attempts' ) ).toHaveValue( '4' );
		await expect( page.locator( 'input#lockout_duration' ) ).toHaveValue( '20' );
		await expect( page.locator( '#pratiks-rescue-url' ) ).toBeVisible();
	} );

	test( 'custom login slug blocks /wp-login.php and routes to custom URL', async ( { page, browser } ) => {
		// 1. Set custom slug via settings page.
		await page.goto( '/wp-admin/options-general.php?page=pratiks-login-shield' );
		await page.locator( 'input#login_slug' ).fill( 'secure-portal' );
		await page.locator( '#submit_settings' ).click();

		await expect( page.locator( '.notice-success' ) ).toBeVisible();

		// 2. Open an unauthenticated context.
		const incognito = await browser.newContext( { storageState: { cookies: [], origins: [] } } );
		const anonPage = await incognito.newPage();

		// 3. Attempt direct access to standard /wp-login.php - should return 404.
		const response = await anonPage.goto( '/wp-login.php' );
		expect( response.status() ).toBe( 404 );

		// 4. Visit custom login URL /secure-portal/ - should load the login form.
		await anonPage.goto( '/secure-portal/' );
		await expect( anonPage.locator( '#loginform' ) ).toBeVisible();
		await expect( anonPage.locator( '#user_login' ) ).toBeVisible();

		await incognito.close();
	} );

	test( 'emergency rescue key bypasses custom login protection', async ( { page, browser } ) => {
		// 1. Enable custom login slug.
		await page.goto( '/wp-admin/options-general.php?page=pratiks-login-shield' );
		await page.locator( 'input#login_slug' ).fill( 'my-secret-vault' );
		await page.locator( '#submit_settings' ).click();

		const rescueUrl = await page.locator( '#pratiks-rescue-url' ).innerText();

		// 2. In unauthenticated browser, visit the rescue URL.
		const incognito = await browser.newContext( { storageState: { cookies: [], origins: [] } } );
		const anonPage = await incognito.newPage();

		await anonPage.goto( rescueUrl );
		await expect( anonPage.locator( '#loginform' ) ).toBeVisible();
		await expect( anonPage.locator( '#user_login' ) ).toBeVisible();

		await incognito.close();
	} );

	test( 'rate limiter tracks failed attempts and locks out IP after reaching limit', async ( { browser } ) => {
		// Set max_attempts = 3 for quick test.
		wp( 'eval', 'update_option( "pratiks_login_shield_settings", array_merge( get_option("pratiks_login_shield_settings", array()), array( "max_attempts" => 3 ) ) );' );

		const incognito = await browser.newContext( { storageState: { cookies: [], origins: [] } } );
		const anonPage = await incognito.newPage();

		// Attempt 1: wrong credentials.
		await anonPage.goto( '/wp-login.php' );
		await anonPage.locator( '#user_login' ).fill( 'admin' );
		await anonPage.locator( '#user_pass' ).fill( 'wrongpass1' );
		await anonPage.locator( '#wp-submit' ).click();
		await expect( anonPage.locator( '#login_error' ) ).toBeVisible();
		await expect( anonPage.locator( '#login_error' ) ).toContainText( 'remaining' );

		// Attempt 2: wrong credentials.
		await anonPage.locator( '#user_login' ).fill( 'admin' );
		await anonPage.locator( '#user_pass' ).fill( 'wrongpass2' );
		await anonPage.locator( '#wp-submit' ).click();
		await expect( anonPage.locator( '#login_error' ) ).toContainText( 'remaining' );

		// Attempt 3: reached limit -> IP locked out.
		await anonPage.locator( '#user_login' ).fill( 'admin' );
		await anonPage.locator( '#user_pass' ).fill( 'wrongpass3' );
		await anonPage.locator( '#wp-submit' ).click();
		await expect( anonPage.locator( '#login_error' ) ).toContainText( 'locked' );

		// Next attempt is immediately blocked with lockout message.
		await anonPage.locator( '#user_login' ).fill( 'admin' );
		await anonPage.locator( '#user_pass' ).fill( 'wrongpass4' );
		await anonPage.locator( '#wp-submit' ).click();
		await expect( anonPage.locator( '#login_error' ) ).toContainText( 'locked' );

		// Check WP-CLI status reports active lockout.
		const cliStatus = wp( 'pratiks-login-shield', 'status' );
		expect( cliStatus ).toContain( 'Active Lockouts:' );

		// Unlock via WP-CLI.
		wp( 'pratiks-login-shield', 'unlock', '127.0.0.1' );

		// Now attempts can be made again without immediate lockout notice.
		await anonPage.goto( '/wp-login.php' );
		await expect( anonPage.locator( '#loginform' ) ).toBeVisible();

		await incognito.close();
	} );

	test( 'WP-CLI reset command restores standard login and clears lockouts', async ( { page, browser } ) => {
		// Set custom slug.
		await page.goto( '/wp-admin/options-general.php?page=pratiks-login-shield' );
		await page.locator( 'input#login_slug' ).fill( 'hidden-door' );
		await page.locator( '#submit_settings' ).click();

		// Run WP-CLI reset.
		const resetOutput = wp( 'pratiks-login-shield', 'reset' );
		expect( resetOutput ).toContain( 'Success' );

		// Verify /wp-login.php works directly again.
		const incognito = await browser.newContext( { storageState: { cookies: [], origins: [] } } );
		const anonPage = await incognito.newPage();

		const res = await anonPage.goto( '/wp-login.php' );
		expect( res.status() ).toBe( 200 );
		await expect( anonPage.locator( '#loginform' ) ).toBeVisible();

		await incognito.close();
	} );
} );
