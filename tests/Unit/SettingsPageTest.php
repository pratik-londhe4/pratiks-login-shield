<?php
/**
 * Tests for SettingsPage.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield\Tests\Unit;

use Brain\Monkey\Functions;
use PratikLondhe\PratiksLoginShield\Settings\SettingsPage;

/**
 * Validates default settings, reserved slugs, and URL helpers.
 */
class SettingsPageTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'wp_generate_password' )->justReturn( 'mocked-rescue-key-64chars' );
		Functions\when( 'site_url' )->alias(
			function ( $path = '' ) {
				return 'http://example.com/' . ltrim( $path, '/' );
			}
		);
		Functions\when( 'home_url' )->alias(
			function ( $path = '' ) {
				return 'http://example.com/' . ltrim( $path, '/' );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			function ( $key, $value = null, $url = '' ) {
				if ( is_array( $key ) ) {
					$url = $value;
					return $url . '?' . http_build_query( $key );
				}
				return $url . '?' . $key . '=' . $value;
			}
		);
		Functions\when( 'trailingslashit' )->alias(
			function ( $str ) {
				return rtrim( $str, '/\\' ) . '/';
			}
		);
	}

	public function test_default_settings(): void {
		$settings = SettingsPage::get_settings();

		$this->assertSame( '', $settings['login_slug'] );
		$this->assertSame( 4, $settings['max_attempts'] );
		$this->assertSame( 20, $settings['lockout_duration'] );
		$this->assertFalse( $settings['disable_xmlrpc'] );
		$this->assertSame( 48, strlen( $settings['rescue_key'] ) );
	}

	public function test_reserved_slugs_contain_critical_paths(): void {
		$reserved = SettingsPage::get_reserved_slugs();

		$this->assertContains( 'wp-admin', $reserved );
		$this->assertContains( 'wp-login.php', $reserved );
		$this->assertContains( 'xmlrpc.php', $reserved );
	}

	public function test_custom_login_url_with_and_without_slug(): void {
		// When no custom slug is set, returns standard wp-login.php.
		$this->assertSame( 'http://example.com/wp-login.php', SettingsPage::get_custom_login_url() );

		// When slug is set.
		update_option(
			SettingsPage::OPTION_NAME,
			array(
				'login_slug'       => 'secret-portal',
				'max_attempts'     => 4,
				'lockout_duration' => 20,
				'disable_xmlrpc'   => false,
				'rescue_key'       => 'test-key',
			)
		);

		update_option( 'permalink_structure', '/%postname%/' );
		$this->assertSame( 'http://example.com/secret-portal/', SettingsPage::get_custom_login_url() );
	}

	public function test_rescue_url_contains_rescue_key(): void {
		update_option(
			SettingsPage::OPTION_NAME,
			array(
				'login_slug'       => 'portal',
				'max_attempts'     => 4,
				'lockout_duration' => 20,
				'disable_xmlrpc'   => false,
				'rescue_key'       => 'secret-token-xyz',
			)
		);

		$url = SettingsPage::get_rescue_url();
		$this->assertStringContainsString( 'shield_rescue=secret-token-xyz', $url );
		$this->assertStringContainsString( 'wp-login.php', $url );
	}
}
