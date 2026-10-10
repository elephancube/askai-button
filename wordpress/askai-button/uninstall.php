<?php
/**
 * Removes everything the plugin stored: one option and two post meta keys.
 *
 * @package Askai_Button
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Deletes the plugin's data on the current site.
 *
 * @return void
 */
function askai_button_uninstall_site() {
	delete_option( 'askai_settings' );
	delete_post_meta_by_key( '_askai_disabled' );
	delete_post_meta_by_key( '_askai_presets' );
}

if ( is_multisite() ) {
	$askai_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $askai_site_ids as $askai_site_id ) {
		switch_to_blog( (int) $askai_site_id );
		askai_button_uninstall_site();
		restore_current_blog();
	}
} else {
	askai_button_uninstall_site();
}
