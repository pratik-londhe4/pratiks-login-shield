<?php
/**
 * Abilities API declarations.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield;

defined( 'ABSPATH' ) || exit;

use PratikLondhe\PratiksLoginShield\Auth\RateLimiter;
use PratikLondhe\PratiksLoginShield\Settings\SettingsPage;

/**
 * Registers AI / agent abilities with WordPress.
 */
class Abilities implements Service {

	/**
	 * Registers abilities if supported.
	 */
	public function register(): void {
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers Login Shield abilities with WordPress.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			'pratiks-login-shield/get-status',
			array(
				'label'       => esc_html__( 'Get Login Shield Status', 'pratiks-login-shield' ),
				'description' => esc_html__( 'Returns current custom login URL, brute force settings, and list of locked out IPs.', 'pratiks-login-shield' ),
				'category'    => 'security',
				'permission'  => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'    => static function () {
					return array(
						'settings' => SettingsPage::get_settings(),
						'lockouts' => RateLimiter::get_active_lockouts(),
					);
				},
			)
		);

		wp_register_ability(
			'pratiks-login-shield/unlock-ip',
			array(
				'label'       => esc_html__( 'Unlock IP Address', 'pratiks-login-shield' ),
				'description' => esc_html__( 'Removes temporary lockout for a specific IP address.', 'pratiks-login-shield' ),
				'category'    => 'security',
				'permission'  => static function () {
					return current_user_can( 'manage_options' );
				},
				'callback'    => static function ( array $args ) {
					$ip = isset( $args['ip'] ) ? sanitize_text_field( $args['ip'] ) : '';
					if ( empty( $ip ) ) {
						return array(
							'success' => false,
							'message' => 'IP is required.',
						);
					}
					$unlocked = RateLimiter::unlock_ip( $ip );
					return array( 'success' => $unlocked );
				},
			)
		);
	}
}
