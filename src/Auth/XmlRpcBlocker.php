<?php
/**
 * Blocks XML-RPC requests to prevent brute force amplification attacks.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield\Auth;

defined( 'ABSPATH' ) || exit;

use PratikLondhe\PratiksLoginShield\Service;
use PratikLondhe\PratiksLoginShield\Settings\SettingsPage;

/**
 * Disables XML-RPC endpoints when enabled in settings.
 */
class XmlRpcBlocker implements Service {

	/**
	 * Registers hooks if XML-RPC protection is active.
	 */
	public function register(): void {
		if ( ! SettingsPage::is_xmlrpc_disabled() ) {
			return;
		}

		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', array( $this, 'filter_xmlrpc_methods' ) );
		add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ) );
	}

	/**
	 * Clears all XML-RPC methods so no calls can execute.
	 *
	 * @param array<string, string> $methods Existing methods.
	 * @return array<string, string> Empty array.
	 */
	public function filter_xmlrpc_methods( array $methods ): array {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $methods );
		return array();
	}

	/**
	 * Strips X-Pingback HTTP header from responses.
	 *
	 * @param array<string, string> $headers HTTP headers.
	 * @return array<string, string> Filtered headers.
	 */
	public function filter_wp_headers( array $headers ): array {
		unset( $headers['X-Pingback'], $headers['x-pingback'] );
		return $headers;
	}
}
