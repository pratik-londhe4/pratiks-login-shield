<?php
/**
 * Brute force rate limiter and IP lockout management.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield\Auth;

defined( 'ABSPATH' ) || exit;

use PratikLondhe\PratiksLoginShield\Service;
use PratikLondhe\PratiksLoginShield\Settings\SettingsPage;
use WP_Error;
use WP_User;

/**
 * Tracks failed login attempts by IP and temporarily locks out violators.
 */
class RateLimiter implements Service {

	/**
	 * Option name for active lockouts.
	 */
	public const OPTION_LOCKOUTS = 'pratiks_login_shield_lockouts';

	/**
	 * Transient prefix for tracking failed attempts before lockout.
	 */
	public const TRANSIENT_ATTEMPTS = 'pratiks_login_shield_attempts_';

	/**
	 * Registers hooks for rate limiting.
	 */
	public function register(): void {
		// Priority 20: check for existing lockout before core authenticates.
		add_filter( 'authenticate', array( $this, 'check_lockout_before_auth' ), 20, 3 );

		// Priority 50: inspect errors after authentication to track failures and show remaining attempts.
		add_filter( 'authenticate', array( $this, 'track_and_annotate_login_errors' ), 50, 3 );

		// Hook into core login failed event.
		add_action( 'wp_login_failed', array( $this, 'handle_login_failed' ) );

		// Hook into successful login to clear attempts.
		add_action( 'wp_login', array( $this, 'handle_login_success' ), 10, 2 );
	}

	/**
	 * Retrieves the validated client IP address.
	 *
	 * @return string Validated IP address or fallback.
	 */
	public static function get_client_ip(): string {
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__ -- IP address needed for rate limiting authentication attempts.
		$raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
			return $raw_ip;
		}

		return '127.0.0.1';
	}

	/**
	 * Retrieves all active lockouts, automatically purging expired ones.
	 *
	 * @return array<string, array{locked_until: int, attempts: int, last_user: string, created_at: int}>
	 */
	public static function get_active_lockouts(): array {
		$lockouts = get_option( self::OPTION_LOCKOUTS, array() );

		if ( ! is_array( $lockouts ) ) {
			return array();
		}

		$current_time = time();
		$active       = array();
		$has_expired  = false;

		foreach ( $lockouts as $ip => $data ) {
			if ( is_array( $data ) && isset( $data['locked_until'] ) && (int) $data['locked_until'] > $current_time ) {
				$active[ (string) $ip ] = $data;
			} else {
				$has_expired = true;
			}
		}

		if ( $has_expired ) {
			update_option( self::OPTION_LOCKOUTS, $active, false );
		}

		return $active;
	}

	/**
	 * Checks if an IP is currently locked out.
	 *
	 * @param string $ip Client IP.
	 * @return bool True if locked out.
	 */
	public static function is_ip_locked( string $ip ): bool {
		$lockouts = self::get_active_lockouts();
		return isset( $lockouts[ $ip ] );
	}

	/**
	 * Retrieves lockout details for an IP if locked.
	 *
	 * @param string $ip Client IP.
	 * @return array{locked_until: int, attempts: int, last_user: string, created_at: int}|null Lockout details.
	 */
	public static function get_lockout_info( string $ip ): ?array {
		$lockouts = self::get_active_lockouts();
		return $lockouts[ $ip ] ?? null;
	}

	/**
	 * Locks out an IP address for the configured lockout duration.
	 *
	 * @param string $ip Client IP.
	 * @param string $username Targeted username.
	 * @param int    $attempts Total attempts.
	 * @return void
	 */
	public static function lock_ip( string $ip, string $username = '', int $attempts = 0 ): void {
		$lockouts     = self::get_active_lockouts();
		$duration_min = SettingsPage::get_lockout_duration();
		$locked_until = time() + ( $duration_min * MINUTE_IN_SECONDS );

		$lockouts[ $ip ] = array(
			'locked_until' => $locked_until,
			'attempts'     => $attempts > 0 ? $attempts : SettingsPage::get_max_attempts(),
			'last_user'    => sanitize_user( $username ),
			'created_at'   => time(),
		);

		update_option( self::OPTION_LOCKOUTS, $lockouts, false );
		self::clear_attempts( $ip );

		/**
		 * Fires when an IP is locked out by Login Shield.
		 *
		 * @param string $ip Locked IP.
		 * @param string $username Targeted username.
		 * @param int $locked_until Timestamp when lockout expires.
		 */
		do_action( 'pratiks_login_shield_ip_locked', $ip, $username, $locked_until );
	}

	/**
	 * Unlocks a specific IP address.
	 *
	 * @param string $ip Client IP to unlock.
	 * @return bool True if unlocked, false if was not locked.
	 */
	public static function unlock_ip( string $ip ): bool {
		$lockouts = self::get_active_lockouts();

		if ( ! isset( $lockouts[ $ip ] ) ) {
			return false;
		}

		unset( $lockouts[ $ip ] );
		update_option( self::OPTION_LOCKOUTS, $lockouts, false );
		self::clear_attempts( $ip );

		return true;
	}

	/**
	 * Clears all active lockouts and attempts.
	 *
	 * @return void
	 */
	public static function unlock_all(): void {
		delete_option( self::OPTION_LOCKOUTS );
	}

	/**
	 * Retrieves the transient key for an IP's failed attempts.
	 *
	 * @param string $ip Client IP.
	 * @return string Transient key.
	 */
	private static function get_attempts_transient_key( string $ip ): string {
		return self::TRANSIENT_ATTEMPTS . md5( $ip );
	}

	/**
	 * Retrieves current failed attempts count for an IP.
	 *
	 * @param string $ip Client IP.
	 * @return int Number of failed attempts.
	 */
	public static function get_attempts_count( string $ip ): int {
		$key   = self::get_attempts_transient_key( $ip );
		$count = get_transient( $key );

		return false !== $count ? (int) $count : 0;
	}

	/**
	 * Increments the failed attempts count for an IP.
	 *
	 * @param string $ip Client IP.
	 * @return int New attempt count.
	 */
	public static function increment_attempts( string $ip ): int {
		$key          = self::get_attempts_transient_key( $ip );
		$count        = self::get_attempts_count( $ip ) + 1;
		$duration_min = SettingsPage::get_lockout_duration();

		set_transient( $key, $count, $duration_min * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Clears the failed attempts counter for an IP.
	 *
	 * @param string $ip Client IP.
	 * @return void
	 */
	public static function clear_attempts( string $ip ): void {
		$key = self::get_attempts_transient_key( $ip );
		delete_transient( $key );
	}

	/**
	 * Blocks authentication if client IP is currently locked out.
	 *
	 * @param WP_User|WP_Error|null $user User object or WP_Error.
	 * @param string                $username Username submitted.
	 * @param string                $password Password submitted.
	 * @return WP_User|WP_Error
	 */
	public function check_lockout_before_auth( $user, string $username, string $password ) {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $username, $password );

		$ip = self::get_client_ip();
		if ( self::is_ip_locked( $ip ) ) {
			$info              = self::get_lockout_info( $ip );
			$remaining_seconds = null !== $info ? max( 0, $info['locked_until'] - time() ) : 0;
			$remaining_minutes = (int) ceil( $remaining_seconds / MINUTE_IN_SECONDS );

			return new WP_Error(
				'pratiks_ip_locked',
				sprintf(
					/* translators: %d: number of minutes until lockout expires */
					esc_html__( 'Access from your IP has been temporarily locked due to too many failed login attempts. Please try again in %d minute(s).', 'pratiks-login-shield' ),
					max( 1, $remaining_minutes )
				)
			);
		}

		return $user;
	}

	/**
	 * Handles core wp_login_failed action.
	 *
	 * @param string $username Username attempted.
	 * @return void
	 */
	public function handle_login_failed( string $username ): void {
		$ip           = self::get_client_ip();
		$max_attempts = SettingsPage::get_max_attempts();
		$count        = self::increment_attempts( $ip );

		if ( $count >= $max_attempts ) {
			self::lock_ip( $ip, $username, $count );
		}
	}

	/**
	 * Annotates login errors with remaining attempts before lockout.
	 *
	 * @param WP_User|WP_Error|null $user User object or error.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error
	 */
	public function track_and_annotate_login_errors( $user, string $username, string $password ) {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $username, $password );

		if ( ! is_wp_error( $user ) ) {
			return $user;
		}

		// Only track/annotate on POST authentication attempts.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return $user;
		}

		// Don't overwrite an existing lockout message.
		if ( 'pratiks_ip_locked' === $user->get_error_code() ) {
			return $user;
		}

		$ip = self::get_client_ip();

		// If just locked out by handle_login_failed.
		if ( self::is_ip_locked( $ip ) ) {
			$info              = self::get_lockout_info( $ip );
			$remaining_seconds = null !== $info ? max( 0, $info['locked_until'] - time() ) : 0;
			$remaining_minutes = (int) ceil( $remaining_seconds / MINUTE_IN_SECONDS );

			return new WP_Error(
				'pratiks_ip_locked',
				sprintf(
					/* translators: %d: number of minutes until lockout expires */
					esc_html__( 'Too many failed login attempts. Access from your IP has been locked for %d minute(s).', 'pratiks-login-shield' ),
					max( 1, $remaining_minutes )
				)
			);
		}

		$attempts     = self::get_attempts_count( $ip );
		$max_attempts = SettingsPage::get_max_attempts();
		$remaining    = max( 0, $max_attempts - $attempts );

		if ( $remaining > 0 ) {
			$user->add(
				'pratiks_attempts_remaining',
				sprintf(
					/* translators: %d: number of attempts remaining */
					esc_html__( 'You have %d login attempt(s) remaining before your IP is temporarily locked.', 'pratiks-login-shield' ),
					$remaining
				)
			);
		}

		return $user;
	}

	/**
	 * Clears failed attempts counter when a user successfully authenticates.
	 *
	 * @param string  $user_login Username.
	 * @param WP_User $user User instance.
	 * @return void
	 */
	public function handle_login_success( string $user_login, WP_User $user ): void {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $user_login, $user );

		$ip = self::get_client_ip();
		self::clear_attempts( $ip );
	}
}
