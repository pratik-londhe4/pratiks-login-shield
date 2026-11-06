<?php
/**
 * Plugin bootstrap.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the services once and registers their hooks.
 */
final class Plugin {

	/**
	 * The running instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Starts the plugin. Runs on `plugins_loaded`.
	 */
	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
	}

	/**
	 * The running instance, for other plugins.
	 *
	 * @return Plugin|null
	 */
	public static function instance(): ?Plugin {
		return self::$instance;
	}

	/**
	 * The plugin's services: one per concern (settings screen, abilities, WP-CLI, ...).
	 *
	 * @return Service[]
	 */
	private function services(): array {
		return array(
			new Auth\LoginUrlHandler(),
			new Auth\RateLimiter(),
			new Auth\XmlRpcBlocker(),
			new Settings\SettingsPage(),
			new Cli\Command(),
			new Abilities(),
			new Privacy(),
		);
	}

	/**
	 * Registers every service.
	 */
	private function register(): void {
		foreach ( $this->services() as $service ) {
			$service->register();
		}
	}
}
