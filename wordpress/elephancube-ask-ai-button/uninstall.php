<?php
/**
 * Removes everything the plugin stored: its settings, two post meta keys and
 * the usage counts.
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
	global $wpdb;

	delete_option( 'askai_settings' );
	delete_post_meta_by_key( '_askai_disabled' );
	delete_post_meta_by_key( '_askai_presets' );

	/*
	 * The usage counts: one option per month and counter, named
	 * `askai_count_<YYYY-MM>_open` or `askai_count_<YYYY-MM>_send_<id of an AI service>`.
	 * The query only narrows the list by prefix; an option is deleted only if
	 * its whole name has exactly that form, so options of other plugins that
	 * happen to share the prefix stay. The pattern is the one of
	 * Askai_Button_Counter::OPTION_PATTERN, which is not loaded here.
	 */
	$askai_pattern = '/^askai_count_([0-9]{4}-(?:0[1-9]|1[0-2]))_(open|send_[A-Za-z0-9_-]{1,40})$/D';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The options API cannot list options by the start of their name. Each matching one is then removed with delete_option().
	$askai_names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'askai_count_' ) . '%'
		)
	);
	foreach ( is_array( $askai_names ) ? $askai_names : array() as $askai_name ) {
		if ( is_string( $askai_name ) && 1 === preg_match( $askai_pattern, $askai_name ) ) {
			delete_option( $askai_name );
		}
	}
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
