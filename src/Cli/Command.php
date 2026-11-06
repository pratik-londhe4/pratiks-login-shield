<?php
/**
 * WP-CLI commands for Login Shield.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield\Cli;

defined( 'ABSPATH' ) || exit;

use PratikLondhe\PratiksLoginShield\Auth\RateLimiter;
use PratikLondhe\PratiksLoginShield\Service;
use PratikLondhe\PratiksLoginShield\Settings\SettingsPage;
use WP_CLI;

/**
 * Manages Login Shield configuration, lockouts, and emergency reset from the command line.
 */
class Command implements Service {

	/**
	 * Registers WP-CLI commands if running in CLI context.
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'pratiks-login-shield', $this );
		}
	}

	/**
	 * Displays current Login Shield status and active lockouts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pratiks-login-shield status
	 *
	 * @subcommand status
	 */
	public function status(): void {
		$settings = SettingsPage::get_settings();
		$slug     = SettingsPage::get_login_slug();
		$lockouts = RateLimiter::get_active_lockouts();

		WP_CLI::line( '=== Login Shield Status ===' );
		WP_CLI::line( 'Custom Login URL: ' . ( '' !== $slug ? SettingsPage::get_custom_login_url() : 'Disabled (using /wp-login.php)' ) );
		WP_CLI::line( 'Max Attempts:     ' . $settings['max_attempts'] );
		WP_CLI::line( 'Lockout Duration: ' . $settings['lockout_duration'] . ' minutes' );
		WP_CLI::line( 'XML-RPC Blocker:  ' . ( $settings['disable_xmlrpc'] ? 'Active (blocked)' : 'Disabled' ) );
		WP_CLI::line( 'Rescue URL:       ' . SettingsPage::get_rescue_url() );
		WP_CLI::line( 'Active Lockouts:  ' . count( $lockouts ) );

		if ( ! empty( $lockouts ) ) {
			WP_CLI::line( "\n--- Active Locked IPs ---" );
			$table_data = array();
			foreach ( $lockouts as $ip => $data ) {
				$remaining_min = (int) ceil( max( 0, (int) $data['locked_until'] - time() ) / MINUTE_IN_SECONDS );
				$table_data[]  = array(
					'IP'          => (string) $ip,
					'Attempts'    => $data['attempts'],
					'Target User' => ! empty( $data['last_user'] ) ? $data['last_user'] : '—',
					'Expires In'  => $remaining_min . ' mins',
				);
			}
			WP_CLI\Utils\format_items( 'table', $table_data, array( 'IP', 'Attempts', 'Target User', 'Expires In' ) );
		}
	}

	/**
	 * Resets Login Shield settings to default and removes all active lockouts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pratiks-login-shield reset
	 *
	 * @subcommand reset
	 */
	public function reset(): void {
		$settings               = SettingsPage::get_settings();
		$settings['login_slug'] = '';
		update_option( SettingsPage::OPTION_NAME, $settings, false );

		RateLimiter::unlock_all();
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Admin CLI command explicitly resetting rewrite rules.
		flush_rewrite_rules();

		WP_CLI::success( 'Login Shield has been reset to defaults. Custom login URL is cleared and all IP lockouts removed.' );
	}

	/**
	 * Unlocks a specific IP address.
	 *
	 * ## OPTIONS
	 *
	 * <ip>
	 * : The IP address to unlock.
	 *
	 * ## EXAMPLES
	 *
	 *     wp pratiks-login-shield unlock 192.168.1.100
	 *
	 * @subcommand unlock
	 * @param string[]             $args Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function unlock( array $args, array $assoc_args ): void {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $assoc_args );

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Please specify an IP address to unlock.' );
			return;
		}

		$ip = sanitize_text_field( $args[0] );

		if ( RateLimiter::unlock_ip( $ip ) ) {
			WP_CLI::success( sprintf( 'Successfully unlocked IP: %s', $ip ) );
		} else {
			WP_CLI::warning( sprintf( 'IP %s was not found in the active lockouts list.', $ip ) );
		}
	}
}
