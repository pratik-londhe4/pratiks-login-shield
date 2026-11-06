<?php
/**
 * Privacy policy declarations, data exporter and eraser.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield;

defined( 'ABSPATH' ) || exit;

use PratikLondhe\PratiksLoginShield\Auth\RateLimiter;

/**
 * Handles GDPR and privacy compliance for temporarily logged IP addresses.
 */
class Privacy implements Service {

	/**
	 * Registers privacy hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Adds privacy policy content suggestion.
	 */
	public function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p class="privacy-policy-tutorial">%s</p><p>%s</p>',
			esc_html__( 'When Login Shield is active on your site, failed authentication attempts and IP lockouts are tracked temporarily to defend against automated brute-force attacks.', 'pratiks-login-shield' ),
			esc_html__( 'Data collected includes client IP addresses, timestamps, and usernames attempted. This data is strictly stored locally within your WordPress database and is automatically purged once temporary lockouts expire.', 'pratiks-login-shield' )
		);

		wp_add_privacy_policy_content( esc_html__( 'Login Shield', 'pratiks-login-shield' ), $content );
	}

	/**
	 * Registers personal data exporter.
	 *
	 * @param array<string, array{exporter_friendly_name: string, callback: callable}> $exporters Exporters.
	 * @return array<string, array{exporter_friendly_name: string, callback: callable}> Filtered exporters.
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['pratiks-login-shield'] = array(
			'exporter_friendly_name' => esc_html__( 'Login Shield Security Data', 'pratiks-login-shield' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);
		return $exporters;
	}

	/**
	 * Exports personal data (lockout records matching user email or username).
	 *
	 * @param string $email_address Email address.
	 * @param int    $page Page number.
	 * @return array{data: array<int, array{group_id: string, group_label: string, item_id: string, data: array<int, array{name: string, value: string}>}>, done: bool}
	 */
	public function export_personal_data( string $email_address, int $page = 1 ): array {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $page );

		$export_items = array();
		$user         = get_user_by( 'email', $email_address );
		$username     = $user ? $user->user_login : $email_address;
		$lockouts     = RateLimiter::get_active_lockouts();

		foreach ( $lockouts as $ip => $data ) {
			if ( $data['last_user'] === $username ) {
				$export_items[] = array(
					'group_id'    => 'pratiks_login_shield_lockouts',
					'group_label' => esc_html__( 'Login Shield Lockouts', 'pratiks-login-shield' ),
					'item_id'     => 'lockout-' . md5( (string) $ip ),
					'data'        => array(
						array(
							'name'  => esc_html__( 'Locked IP Address', 'pratiks-login-shield' ),
							'value' => (string) $ip,
						),
						array(
							'name'  => esc_html__( 'Failed Attempts', 'pratiks-login-shield' ),
							'value' => (string) $data['attempts'],
						),
						array(
							'name'  => esc_html__( 'Targeted Username', 'pratiks-login-shield' ),
							'value' => (string) $data['last_user'],
						),
						array(
							'name'  => esc_html__( 'Lockout Expiration', 'pratiks-login-shield' ),
							'value' => wp_date( 'Y-m-d H:i:s', (int) $data['locked_until'] ),
						),
					),
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Registers personal data eraser.
	 *
	 * @param array<string, array{eraser_friendly_name: string, callback: callable}> $erasers Erasers.
	 * @return array<string, array{eraser_friendly_name: string, callback: callable}> Filtered erasers.
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['pratiks-login-shield'] = array(
			'eraser_friendly_name' => esc_html__( 'Login Shield Security Data', 'pratiks-login-shield' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/**
	 * Erases personal data associated with the user email.
	 *
	 * @param string $email_address Email address.
	 * @param int    $page Page number.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase_personal_data( string $email_address, int $page = 1 ): array {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $page );

		$user     = get_user_by( 'email', $email_address );
		$username = $user ? $user->user_login : $email_address;
		$lockouts = RateLimiter::get_active_lockouts();
		$removed  = false;

		foreach ( $lockouts as $ip => $data ) {
			if ( $data['last_user'] === $username ) {
				RateLimiter::unlock_ip( (string) $ip );
				$removed = true;
			}
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
