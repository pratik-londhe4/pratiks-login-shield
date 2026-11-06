<?php
/**
 * Removes everything Login Shield stored. Runs when the plugin is deleted from wp-admin.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

( static function () {
	// Clean up single-site or multisite options.
	if ( is_multisite() ) {
		$site_ids = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			delete_option( 'pratiks_login_shield_settings' );
			delete_option( 'pratiks_login_shield_lockouts' );
			restore_current_blog();
		}
	} else {
		delete_option( 'pratiks_login_shield_settings' );
		delete_option( 'pratiks_login_shield_lockouts' );
	}
} )();
