<?php
/**
 * Intercepts wp-login.php and rewrites authentication URLs to a custom slug.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield\Auth;

defined( 'ABSPATH' ) || exit;

use PratikLondhe\PratiksLoginShield\Service;
use PratikLondhe\PratiksLoginShield\Settings\SettingsPage;
use WP_Query;

/**
 * Handles custom login slug routing and wp-login.php blocking.
 */
class LoginUrlHandler implements Service {

	/**
	 * Whether direct access to wp-login.php was detected on this request.
	 *
	 * @var bool
	 */
	private bool $is_intercepted_direct_login = false;

	/**
	 * Registers hooks for URL rewriting and request interception.
	 */
	public function register(): void {
		add_action( 'plugins_loaded', array( $this, 'detect_login_request' ), 1 );
		add_action( 'wp_loaded', array( $this, 'handle_intercepted_request' ) );

		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'site_url', array( $this, 'filter_site_url' ), 10, 3 );
		add_filter( 'network_site_url', array( $this, 'filter_network_site_url' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_wp_redirect' ), 10, 2 );
		add_filter( 'lostpassword_url', array( $this, 'filter_lostpassword_url' ), 10, 2 );
		add_filter( 'register_url', array( $this, 'filter_register_url' ), 10, 1 );
	}

	/**
	 * Checks if login URL protection is currently active.
	 *
	 * @return bool True if active.
	 */
	public static function is_enabled(): bool {
		if ( defined( 'PRATIKS_LOGIN_SHIELD_DISABLE' ) && PRATIKS_LOGIN_SHIELD_DISABLE ) {
			return false;
		}

		$slug = SettingsPage::get_login_slug();
		return '' !== $slug;
	}

	/**
	 * Checks if the current request is an emergency rescue bypass.
	 *
	 * @return bool True if rescue key is valid.
	 */
	public static function is_rescue_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['shield_rescue'] ) ) {
			return false;
		}

		$configured_key = SettingsPage::get_rescue_key();
		if ( '' === $configured_key ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provided_key = sanitize_text_field( wp_unslash( $_GET['shield_rescue'] ) );

		return hash_equals( $configured_key, $provided_key );
	}

	/**
	 * Inspects the incoming request URL on plugins_loaded to intercept or route.
	 */
	public function detect_login_request(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Never intercept CLI requests.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// Allow emergency rescue requests.
		if ( self::is_rescue_request() ) {
			return;
		}

		// Allow post password submissions.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && 'postpass' === $_GET['action'] ) {
			return;
		}

		global $pagenow;

		$slug                 = SettingsPage::get_login_slug();
		$raw_uri              = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$parsed_path          = (string) wp_parse_url( $raw_uri, PHP_URL_PATH );
		$clean_path           = untrailingslashit( $parsed_path );
		$relative_custom_path = untrailingslashit( (string) wp_parse_url( home_url( '/' . $slug ), PHP_URL_PATH ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_plain_slug_param = '' !== $slug && ! get_option( 'permalink_structure' ) && isset( $_GET[ $slug ] );

		$is_direct_wp_login = false !== strpos( $raw_uri, 'wp-login.php' ) && ! is_admin();
		$is_custom_slug     = ( '' !== $slug && $clean_path === $relative_custom_path ) || $has_plain_slug_param;

		if ( $is_direct_wp_login ) {
			$this->is_intercepted_direct_login = true;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required to mask wp-login.php from direct access.
			$pagenow = 'index.php';
		} elseif ( $is_custom_slug ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required to route custom login URL to login handler.
			$pagenow                = 'wp-login.php';
			$_SERVER['SCRIPT_NAME'] = '/' . $slug;
		}
	}

	/**
	 * Handles intercepted request on wp_loaded.
	 */
	public function handle_intercepted_request(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( self::is_rescue_request() ) {
			return;
		}

		// If direct wp-login.php access was intercepted, return 404.
		if ( $this->is_intercepted_direct_login ) {
			$this->render_404_and_exit();
		}

		global $pagenow;

		if ( 'wp-login.php' === $pagenow ) {
			// If already logged in and not logging out, redirect to admin.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$request_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

			if ( is_user_logged_in() && ! in_array( $request_action, array( 'logout', 'postpass' ), true ) ) {
				wp_safe_redirect( admin_url() );
				exit;
			}

			// Render the WordPress login screen in global scope.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			global $error, $interim_login, $action, $user_login;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.NonceVerification.Recommended -- Initializing action global for wp-login.php inclusion.
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : 'login';
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@require_once ABSPATH . 'wp-login.php';
			exit;
		}
	}

	/**
	 * Renders a standard 404 Not Found response and exits.
	 */
	private function render_404_and_exit(): void {
		status_header( 404 );
		nocache_headers();

		wp_die(
			esc_html__( 'Page not found.', 'pratiks-login-shield' ),
			esc_html__( 'Not Found', 'pratiks-login-shield' ),
			array( 'response' => 404 )
		);
	}

	/**
	 * Rewrites login URL to custom slug.
	 *
	 * @param string $login_url Existing login URL.
	 * @param string $redirect Redirect URL parameter.
	 * @param bool   $force_reauth Whether to force reauth.
	 * @return string Rewritten login URL.
	 */
	public function filter_login_url( string $login_url, string $redirect = '', bool $force_reauth = false ): string {
		if ( ! self::is_enabled() ) {
			return $login_url;
		}

		$custom_base = SettingsPage::get_custom_login_url();
		$args        = array();

		if ( ! empty( $redirect ) ) {
			$args['redirect_to'] = rawurlencode( $redirect );
		}

		if ( $force_reauth ) {
			$args['reauth'] = '1';
		}

		// Preserve any other query parameters from the original login URL.
		$parsed_query = (string) wp_parse_url( $login_url, PHP_URL_QUERY );
		if ( ! empty( $parsed_query ) ) {
			wp_parse_str( $parsed_query, $query_params );
			foreach ( $query_params as $key => $val ) {
				if ( is_string( $key ) && is_string( $val ) && ! isset( $args[ $key ] ) ) {
					$args[ $key ] = $val;
				}
			}
		}

		return ! empty( $args ) ? add_query_arg( $args, $custom_base ) : $custom_base;
	}

	/**
	 * Rewrites site_url when referencing wp-login.php.
	 *
	 * @param string      $url The generated site URL.
	 * @param string      $path The relative path requested.
	 * @param string|null $scheme The scheme.
	 * @return string Filtered URL.
	 */
	public function filter_site_url( string $url, string $path, ?string $scheme = null ): string {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $scheme );

		if ( ! self::is_enabled() ) {
			return $url;
		}

		// Don't rewrite postpass action or rescue requests.
		if ( false !== strpos( $path, 'action=postpass' ) || false !== strpos( $url, 'shield_rescue=' ) || false !== strpos( $path, 'shield_rescue=' ) ) {
			return $url;
		}

		if ( false !== strpos( $url, 'wp-login.php' ) ) {
			$slug = SettingsPage::get_login_slug();

			if ( get_option( 'permalink_structure' ) ) {
				return str_replace( 'wp-login.php', trailingslashit( $slug ), $url );
			}

			return str_replace( 'wp-login.php', '?' . $slug, $url );
		}

		return $url;
	}

	/**
	 * Rewrites network_site_url when referencing wp-login.php.
	 *
	 * @param string      $url Generated network site URL.
	 * @param string      $path Relative path.
	 * @param string|null $scheme Scheme.
	 * @return string Filtered URL.
	 */
	public function filter_network_site_url( string $url, string $path, ?string $scheme = null ): string {
		return $this->filter_site_url( $url, $path, $scheme );
	}

	/**
	 * Rewrites wp_redirect locations targeting wp-login.php.
	 *
	 * @param string $location Destination redirect URL.
	 * @param int    $status HTTP status code.
	 * @return string Filtered redirect URL.
	 */
	public function filter_wp_redirect( string $location, int $status = 302 ): string {
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		unset( $status );

		if ( ! self::is_enabled() ) {
			return $location;
		}

		if ( false !== strpos( $location, 'wp-login.php' ) && false === strpos( $location, 'action=postpass' ) && false === strpos( $location, 'shield_rescue=' ) ) {
			$slug = SettingsPage::get_login_slug();

			if ( get_option( 'permalink_structure' ) ) {
				return str_replace( 'wp-login.php', trailingslashit( $slug ), $location );
			}

			return str_replace( 'wp-login.php', '?' . $slug, $location );
		}

		return $location;
	}

	/**
	 * Rewrites lostpassword_url to custom login slug.
	 *
	 * @param string $lostpassword_url Default lost password URL.
	 * @param string $redirect Redirect URL parameter.
	 * @return string Filtered URL.
	 */
	public function filter_lostpassword_url( string $lostpassword_url, string $redirect = '' ): string {
		if ( ! self::is_enabled() ) {
			return $lostpassword_url;
		}

		$base = SettingsPage::get_custom_login_url();
		$args = array( 'action' => 'lostpassword' );

		if ( ! empty( $redirect ) ) {
			$args['redirect_to'] = rawurlencode( $redirect );
		}

		return add_query_arg( $args, $base );
	}

	/**
	 * Rewrites register_url to custom login slug.
	 *
	 * @param string $register_url Default register URL.
	 * @return string Filtered URL.
	 */
	public function filter_register_url( string $register_url ): string {
		if ( ! self::is_enabled() ) {
			return $register_url;
		}

		$base = SettingsPage::get_custom_login_url();
		return add_query_arg( 'action', 'register', $base );
	}
}
