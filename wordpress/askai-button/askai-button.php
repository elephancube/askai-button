<?php
/**
 * Plugin Name:       AskAI Button
 * Plugin URI:        https://github.com/elephancube/askai-button
 * Description:       Adds a floating "Ask AI" button. The visitor's question and your site URL are handed to ChatGPT, Claude or Google AI Mode in a new tab. No API key, no external requests by the plugin, and the question is never stored.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Elephancube Inc.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       askai-button
 * Domain Path:       /languages
 *
 * @package Askai_Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASKAI_BUTTON_VERSION', '0.1.0' );
define( 'ASKAI_BUTTON_FILE', __FILE__ );
define( 'ASKAI_BUTTON_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASKAI_BUTTON_URL', plugin_dir_url( __FILE__ ) );

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
	load_plugin_textdomain( 'askai-button', false, dirname( plugin_basename( ASKAI_BUTTON_FILE ) ) . '/languages' );
}
add_action( 'init', 'askai_button_load_textdomain' );
