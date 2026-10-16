<?php
/**
 * Loads the plugin. Included by the main file once it is clear that no other
 * copy of the plugin is loaded.
 *
 * @package Askai_Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ASKAI_BUTTON_DIR . 'includes/class-settings.php';
require_once ASKAI_BUTTON_DIR . 'includes/class-multilingual.php';
require_once ASKAI_BUTTON_DIR . 'includes/class-frontend.php';
require_once ASKAI_BUTTON_DIR . 'includes/class-counter.php';

/**
 * Starts the plugin once all plugins are loaded.
 *
 * @return void
 */
function askai_button_boot() {
	Askai_Button_Frontend::register();
	Askai_Button_Multilingual::register();
	Askai_Button_Counter::register();
	if ( is_admin() ) {
		require_once ASKAI_BUTTON_DIR . 'includes/class-admin.php';
		Askai_Button_Admin::register();
	}
}
add_action( 'plugins_loaded', 'askai_button_boot' );

/**
 * Makes the translations in the `languages` folder available on WordPress 6.4 to 6.7.
 *
 * From WordPress 6.8 on nothing is needed: WordPress reads the "Domain Path"
 * header and finds the files of the plugin by itself. Older versions only
 * look in `wp-content/languages/plugins`, where translations installed from
 * WordPress.org are stored, and have to be told about the folder of the
 * plugin. A translation installed from WordPress.org takes precedence on
 * every version.
 *
 * @return void
 */
function askai_button_load_textdomain() {
	if ( version_compare( get_bloginfo( 'version' ), '6.8', '>=' ) ) {
		return;
	}
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Reached on WordPress 6.4 to 6.7 only, which do not load the bundled translations by themselves.
	load_plugin_textdomain( 'elephancube-ask-ai-button', false, dirname( plugin_basename( ASKAI_BUTTON_FILE ) ) . '/languages' );
}
add_action( 'init', 'askai_button_load_textdomain' );
