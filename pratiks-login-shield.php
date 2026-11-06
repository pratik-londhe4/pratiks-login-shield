<?php
/**
 * Plugin Name:       Pratiks Login Shield
 * Plugin URI:        https://wordpress.org/plugins/pratiks-login-shield/
 * Description:       Protect WordPress from brute force attacks with custom login URLs, rate limiting lockouts, and emergency anti-lockout recovery.
 * Version:           0.1.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Pratik Londhe
 * Author URI:        https://profiles.wordpress.org/pratiklondhe/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pratiks-login-shield
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.1';
const PLUGIN_FILE = __FILE__;

// PSR-4: PratikLondhe\PratiksLoginShield\Foo\Bar lives in src/Foo/Bar.php.
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

Plugin::boot();
