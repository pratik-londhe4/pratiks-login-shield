<?php
/**
 * Admin settings page for Login Shield.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield\Settings;

defined( 'ABSPATH' ) || exit;

use PratikLondhe\PratiksLoginShield\Auth\RateLimiter;
use PratikLondhe\PratiksLoginShield\Service;

/**
 * Renders and handles settings for custom login URL, brute force protection, and rescue key.
 */
class SettingsPage implements Service {

	/**
	 * Settings option name.
	 */
	public const OPTION_NAME = 'pratiks_login_shield_settings';

	/**
	 * Menu page slug.
	 */
	public const MENU_SLUG = 'pratiks-login-shield';

	/**
	 * Registers admin menu and hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_init', array( $this, 'handle_form_actions' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( \PratikLondhe\PratiksLoginShield\PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Adds a "Settings" link on the Plugins list table.
	 *
	 * @param array<string, string> $links Existing action links.
	 * @return array<string, string> Modified action links.
	 */
	public function add_settings_link( array $links ): array {
		$url           = admin_url( 'options-general.php?page=' . self::MENU_SLUG );
		$settings_link = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'pratiks-login-shield' ) );
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Registers the options sub-page under Settings.
	 */
	public function register_menu_page(): void {
		add_options_page(
			esc_html__( 'Login Shield', 'pratiks-login-shield' ),
			esc_html__( 'Login Shield', 'pratiks-login-shield' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Retrieves all plugin settings with defaults.
	 *
	 * @return array{login_slug: string, max_attempts: int, lockout_duration: int, disable_xmlrpc: bool, rescue_key: string}
	 */
	public static function get_settings(): array {
		$defaults = array(
			'login_slug'       => '',
			'max_attempts'     => 4,
			'lockout_duration' => 20,
			'disable_xmlrpc'   => false,
			'rescue_key'       => '',
		);

		$saved = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );

		// Generate rescue key if missing.
		if ( empty( $settings['rescue_key'] ) ) {
			$settings['rescue_key'] = bin2hex( random_bytes( 24 ) );
			update_option( self::OPTION_NAME, $settings, false );
		}

		return $settings;
	}

	/**
	 * Gets the custom login slug.
	 *
	 * @return string Custom slug or empty string.
	 */
	public static function get_login_slug(): string {
		$settings = self::get_settings();
		return (string) $settings['login_slug'];
	}

	/**
	 * Gets the maximum allowed failed attempts.
	 *
	 * @return int Max attempts.
	 */
	public static function get_max_attempts(): int {
		$settings = self::get_settings();
		$max      = (int) $settings['max_attempts'];
		return $max > 0 ? $max : 4;
	}

	/**
	 * Gets the lockout duration in minutes.
	 *
	 * @return int Duration in minutes.
	 */
	public static function get_lockout_duration(): int {
		$settings = self::get_settings();
		$duration = (int) $settings['lockout_duration'];
		return $duration > 0 ? $duration : 20;
	}

	/**
	 * Checks if XML-RPC protection is active.
	 *
	 * @return bool True if disabled.
	 */
	public static function is_xmlrpc_disabled(): bool {
		$settings = self::get_settings();
		return (bool) $settings['disable_xmlrpc'];
	}

	/**
	 * Gets the secret rescue key.
	 *
	 * @return string Rescue token.
	 */
	public static function get_rescue_key(): string {
		$settings = self::get_settings();
		return (string) $settings['rescue_key'];
	}

	/**
	 * Gets the full rescue URL.
	 *
	 * @return string Rescue URL.
	 */
	public static function get_rescue_url(): string {
		$base = home_url( '/wp-login.php' );
		return add_query_arg( 'shield_rescue', self::get_rescue_key(), $base );
	}

	/**
	 * Gets the full custom login URL if configured.
	 *
	 * @return string Custom login URL.
	 */
	public static function get_custom_login_url(): string {
		$slug = self::get_login_slug();
		if ( '' === $slug ) {
			return site_url( 'wp-login.php' );
		}

		if ( get_option( 'permalink_structure' ) ) {
			return trailingslashit( home_url( '/' . $slug ) );
		}

		return add_query_arg( $slug, '', home_url( '/' ) );
	}

	/**
	 * List of reserved slugs that cannot be used as the custom login slug.
	 *
	 * @return string[] Reserved slugs.
	 */
	public static function get_reserved_slugs(): array {
		return array(
			'wp-admin',
			'wp-login',
			'wp-login.php',
			'wp-content',
			'wp-includes',
			'wp-comments-post.php',
			'xmlrpc.php',
			'admin',
			'dashboard',
			'feed',
			'rss',
			'embed',
		);
	}

	/**
	 * Handles POST submissions for settings and actions.
	 */
	public function handle_form_actions(): void {
		if ( ! isset( $_POST['pratiks_login_shield_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Login Shield.', 'pratiks-login-shield' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['pratiks_login_shield_action'] ) );

		if ( 'save_settings' === $action ) {
			check_admin_referer( 'pratiks_login_shield_save_settings_nonce' );

			$current_settings = self::get_settings();
			$raw_slug         = isset( $_POST['login_slug'] ) ? sanitize_title( wp_unslash( $_POST['login_slug'] ) ) : '';
			$max_attempts     = isset( $_POST['max_attempts'] ) ? absint( $_POST['max_attempts'] ) : 4;
			$lockout_duration = isset( $_POST['lockout_duration'] ) ? absint( $_POST['lockout_duration'] ) : 20;
			$disable_xmlrpc   = ! empty( $_POST['disable_xmlrpc'] );

			if ( in_array( $raw_slug, self::get_reserved_slugs(), true ) ) {
				$redirect_url = add_query_arg(
					array(
						'page'   => self::MENU_SLUG,
						'status' => 'reserved_slug',
					),
					admin_url( 'options-general.php' )
				);
				wp_safe_redirect( $redirect_url );
				exit;
			}

			$new_settings = array(
				'login_slug'       => $raw_slug,
				'max_attempts'     => max( 1, min( 100, $max_attempts ) ),
				'lockout_duration' => max( 1, min( 1440, $lockout_duration ) ),
				'disable_xmlrpc'   => $disable_xmlrpc,
				'rescue_key'       => $current_settings['rescue_key'],
			);

			update_option( self::OPTION_NAME, $new_settings, false );

			// Flush rewrite rules if slug changed.
			if ( $raw_slug !== $current_settings['login_slug'] ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Flushed only on explicit settings change of login slug.
				flush_rewrite_rules();
			}

			$redirect_url = add_query_arg(
				array(
					'page'   => self::MENU_SLUG,
					'status' => 'saved',
				),
				admin_url( 'options-general.php' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'regenerate_rescue_key' === $action ) {
			check_admin_referer( 'pratiks_login_shield_regenerate_key_nonce' );

			$current_settings               = self::get_settings();
			$current_settings['rescue_key'] = bin2hex( random_bytes( 24 ) );
			update_option( self::OPTION_NAME, $current_settings, false );

			$redirect_url = add_query_arg(
				array(
					'page'   => self::MENU_SLUG,
					'status' => 'key_regenerated',
				),
				admin_url( 'options-general.php' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'unlock_ip' === $action ) {
			check_admin_referer( 'pratiks_login_shield_unlock_ip_nonce' );

			$ip_to_unlock = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
			if ( ! empty( $ip_to_unlock ) ) {
				RateLimiter::unlock_ip( $ip_to_unlock );
			}

			$redirect_url = add_query_arg(
				array(
					'page'   => self::MENU_SLUG,
					'status' => 'unlocked',
				),
				admin_url( 'options-general.php' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( 'unlock_all' === $action ) {
			check_admin_referer( 'pratiks_login_shield_unlock_all_nonce' );

			RateLimiter::unlock_all();

			$redirect_url = add_query_arg(
				array(
					'page'   => self::MENU_SLUG,
					'status' => 'all_unlocked',
				),
				admin_url( 'options-general.php' )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Renders status notices based on query parameter.
	 */
	public function render_admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_' . self::MENU_SLUG !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		if ( 'saved' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'pratiks-login-shield' ) . '</p></div>';
		} elseif ( 'reserved_slug' === $status ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Error: The requested login slug is reserved by WordPress. Please choose a different slug.', 'pratiks-login-shield' ) . '</p></div>';
		} elseif ( 'key_regenerated' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'New emergency rescue key generated. Please save your new rescue link.', 'pratiks-login-shield' ) . '</p></div>';
		} elseif ( 'unlocked' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'IP address unlocked successfully.', 'pratiks-login-shield' ) . '</p></div>';
		} elseif ( 'all_unlocked' === $status ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All IP lockouts have been cleared.', 'pratiks-login-shield' ) . '</p></div>';
		}
	}

	/**
	 * Renders the settings screen HTML.
	 */
	public function render_page(): void {
		$settings        = self::get_settings();
		$active_lockouts = RateLimiter::get_active_lockouts();
		$custom_slug     = $settings['login_slug'];
		$rescue_url      = self::get_rescue_url();
		$base_url        = trailingslashit( home_url() );
		$is_shielded     = '' !== $custom_slug;
		?>
		<div class="wrap pratiks-login-shield-wrap">
			<h1><?php esc_html_e( 'Login Shield', 'pratiks-login-shield' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Hides your WordPress login URL from automated scanners and rate-limits brute force attempts.', 'pratiks-login-shield' ); ?>
			</p>

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 20px;">
				<div>
					<form method="post" action="">
						<?php wp_nonce_field( 'pratiks_login_shield_save_settings_nonce' ); ?>
						<input type="hidden" name="pratiks_login_shield_action" value="save_settings">

						<div class="card" style="margin-top: 0; max-width: 100%;">
							<h2><?php esc_html_e( 'Custom Login URL', 'pratiks-login-shield' ); ?></h2>
							<p>
								<?php esc_html_e( 'Change the default /wp-login.php to a secret address. Direct visits to /wp-login.php will be returned as 404 Not Found.', 'pratiks-login-shield' ); ?>
							</p>

							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="login_slug"><?php esc_html_e( 'Custom Login Slug', 'pratiks-login-shield' ); ?></label></th>
									<td>
										<code><?php echo esc_html( $base_url ); ?></code>
										<input name="login_slug" type="text" id="login_slug" value="<?php echo esc_attr( $custom_slug ); ?>" class="regular-text" placeholder="e.g. secret-portal">
										<p class="description">
											<?php esc_html_e( 'Leave blank to use standard WordPress login URL (/wp-login.php). Only letters, numbers, and hyphens.', 'pratiks-login-shield' ); ?>
										</p>
										<?php if ( $is_shielded ) : ?>
											<p style="margin-top: 8px;">
												<strong><?php esc_html_e( 'Your active login URL:', 'pratiks-login-shield' ); ?></strong>
												<a href="<?php echo esc_url( self::get_custom_login_url() ); ?>" target="_blank" rel="noopener noreferrer">
													<?php echo esc_html( self::get_custom_login_url() ); ?>
												</a>
											</p>
										<?php endif; ?>
									</td>
								</tr>
							</table>
						</div>

						<div class="card" style="max-width: 100%; margin-top: 20px;">
							<h2><?php esc_html_e( 'Brute Force Rate Limiting', 'pratiks-login-shield' ); ?></h2>
							<p>
								<?php esc_html_e( 'Automatically blocks repetitive failed login attempts from malicious IPs.', 'pratiks-login-shield' ); ?>
							</p>

							<table class="form-table" role="presentation">
								<tr>
									<th scope="row"><label for="max_attempts"><?php esc_html_e( 'Max Login Attempts', 'pratiks-login-shield' ); ?></label></th>
									<td>
										<input name="max_attempts" type="number" id="max_attempts" value="<?php echo esc_attr( (string) $settings['max_attempts'] ); ?>" min="1" max="100" class="small-text">
										<?php esc_html_e( 'failed attempts before temporary lockout.', 'pratiks-login-shield' ); ?>
										<p class="description"><?php esc_html_e( 'Default: 4 attempts.', 'pratiks-login-shield' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="lockout_duration"><?php esc_html_e( 'Lockout Duration', 'pratiks-login-shield' ); ?></label></th>
									<td>
										<input name="lockout_duration" type="number" id="lockout_duration" value="<?php echo esc_attr( (string) $settings['lockout_duration'] ); ?>" min="1" max="1440" class="small-text">
										<?php esc_html_e( 'minutes.', 'pratiks-login-shield' ); ?>
										<p class="description"><?php esc_html_e( 'Default: 20 minutes.', 'pratiks-login-shield' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'XML-RPC Protection', 'pratiks-login-shield' ); ?></th>
									<td>
										<label for="disable_xmlrpc">
											<input name="disable_xmlrpc" type="checkbox" id="disable_xmlrpc" value="1" <?php checked( $settings['disable_xmlrpc'] ); ?>>
											<?php esc_html_e( 'Disable XML-RPC authentication and pingbacks', 'pratiks-login-shield' ); ?>
										</label>
										<p class="description">
											<?php esc_html_e( 'Blocks brute force amplification attacks targeting xmlrpc.php. Recommended unless you use the WordPress mobile app or Jetpack.', 'pratiks-login-shield' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>

						<?php submit_button( esc_html__( 'Save Changes', 'pratiks-login-shield' ), 'primary', 'submit_settings' ); ?>
					</form>

					<div class="card" style="max-width: 100%; margin-top: 20px;">
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<h2 style="margin: 0;"><?php esc_html_e( 'Active IP Lockouts', 'pratiks-login-shield' ); ?></h2>
							<?php if ( ! empty( $active_lockouts ) ) : ?>
								<form method="post" action="" style="display: inline-block;">
									<?php wp_nonce_field( 'pratiks_login_shield_unlock_all_nonce' ); ?>
									<input type="hidden" name="pratiks_login_shield_action" value="unlock_all">
									<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to unlock all IPs?', 'pratiks-login-shield' ) ); ?>');">
										<?php esc_html_e( 'Unlock All IPs', 'pratiks-login-shield' ); ?>
									</button>
								</form>
							<?php endif; ?>
						</div>

						<p><?php esc_html_e( 'IP addresses currently locked out due to excessive failed login attempts.', 'pratiks-login-shield' ); ?></p>

						<?php if ( empty( $active_lockouts ) ) : ?>
							<p style="color: #007017; font-weight: 600;">
								&#10003; <?php esc_html_e( 'No IP addresses are currently locked out.', 'pratiks-login-shield' ); ?>
							</p>
						<?php else : ?>
							<table class="widefat striped" style="margin-top: 10px;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'IP Address', 'pratiks-login-shield' ); ?></th>
										<th><?php esc_html_e( 'Failed Attempts', 'pratiks-login-shield' ); ?></th>
										<th><?php esc_html_e( 'Targeted Username', 'pratiks-login-shield' ); ?></th>
										<th><?php esc_html_e( 'Locked Until', 'pratiks-login-shield' ); ?></th>
										<th><?php esc_html_e( 'Action', 'pratiks-login-shield' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $active_lockouts as $locked_ip => $data ) : ?>
										<?php
										$remaining_sec = max( 0, (int) $data['locked_until'] - time() );
										$remaining_min = (int) ceil( $remaining_sec / MINUTE_IN_SECONDS );
										?>
										<tr>
											<td><code><?php echo esc_html( (string) $locked_ip ); ?></code></td>
											<td><?php echo esc_html( (string) $data['attempts'] ); ?></td>
											<td><?php echo esc_html( ! empty( $data['last_user'] ) ? (string) $data['last_user'] : '—' ); ?></td>
											<td>
												<?php
												printf(
													/* translators: 1: formatted datetime, 2: minutes remaining */
													esc_html__( '%1$s (in ~%2$d min)', 'pratiks-login-shield' ),
													esc_html( wp_date( 'Y-m-d H:i:s', (int) $data['locked_until'] ) ),
													(int) $remaining_min
												);
												?>
											</td>
											<td>
												<form method="post" action="" style="display:inline-block;">
													<?php wp_nonce_field( 'pratiks_login_shield_unlock_ip_nonce' ); ?>
													<input type="hidden" name="pratiks_login_shield_action" value="unlock_ip">
													<input type="hidden" name="ip" value="<?php echo esc_attr( (string) $locked_ip ); ?>">
													<button type="submit" class="button button-small">
														<?php esc_html_e( 'Unlock', 'pratiks-login-shield' ); ?>
													</button>
												</form>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<div>
					<div class="card" style="margin-top: 0; border-left: 4px solid #2271b1;">
						<h3 style="margin-top: 0;"><?php esc_html_e( 'Anti-Lockout Emergency Key', 'pratiks-login-shield' ); ?></h3>
						<p>
							<?php esc_html_e( 'If you forget your custom login slug, use this emergency URL to bypass the shield and access the standard login screen.', 'pratiks-login-shield' ); ?>
						</p>

						<div style="background: #f0f0f1; padding: 10px; border-radius: 4px; word-break: break-all; margin: 12px 0;">
							<code id="pratiks-rescue-url"><?php echo esc_html( $rescue_url ); ?></code>
						</div>

						<p>
							<button type="button" class="button button-secondary" id="pratiks-copy-rescue-btn" onclick="navigator.clipboard.writeText(document.getElementById('pratiks-rescue-url').innerText).then(() => alert('<?php echo esc_js( __( 'Rescue URL copied to clipboard!', 'pratiks-login-shield' ) ); ?>'));">
								<?php esc_html_e( 'Copy Rescue URL', 'pratiks-login-shield' ); ?>
							</button>
						</p>

						<hr style="margin: 16px 0;">

						<form method="post" action="">
							<?php wp_nonce_field( 'pratiks_login_shield_regenerate_key_nonce' ); ?>
							<input type="hidden" name="pratiks_login_shield_action" value="regenerate_rescue_key">
							<p>
								<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Generating a new rescue key will invalidate your previous emergency URL. Continue?', 'pratiks-login-shield' ) ); ?>');">
									<?php esc_html_e( 'Regenerate Rescue Key', 'pratiks-login-shield' ); ?>
								</button>
							</p>
						</form>
					</div>

					<div class="card" style="margin-top: 20px;">
						<h3 style="margin-top: 0;"><?php esc_html_e( 'Emergency WP-CLI Recovery', 'pratiks-login-shield' ); ?></h3>
						<p>
							<?php esc_html_e( 'Have server or terminal access? You can immediately clear all lockouts and reset the login URL with one command:', 'pratiks-login-shield' ); ?>
						</p>
						<pre style="background: #23282d; color: #fff; padding: 10px; border-radius: 4px; overflow-x: auto;"><code>wp pratiks-login-shield reset</code></pre>
						<p class="description">
							<?php esc_html_e( 'Or add define( \'PRATIKS_LOGIN_SHIELD_DISABLE\', true ); to wp-config.php.', 'pratiks-login-shield' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
