<?php
/**
 * Plugin Name:       AskAI Button
 * Description:       Adds a floating "Ask AI" button. The visitor's question and your site URL are handed to ChatGPT, Claude or Google AI Mode in a new tab. No API, no server-side processing, no tracking.
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
require_once ASKAI_BUTTON_DIR . 'includes/class-frontend.php';

/**
 * Starts the plugin once all plugins are loaded.
 *
 * @return void
 */
function askai_button_boot() {
	Askai_Button_Frontend::register();
	if ( is_admin() ) {
		require_once ASKAI_BUTTON_DIR . 'includes/class-admin.php';
		Askai_Button_Admin::register();
	}
}
add_action( 'plugins_loaded', 'askai_button_boot' );

/**
 * Makes the translations in the `languages` folder available.
 *
 * WordPress finds translations installed from WordPress.org by itself, but
 * not the files a plugin ships with. Up to WordPress 6.6 this call loads
 * them; from 6.7 on it only tells WordPress where they are, and they are
 * loaded when the first string is translated. A translation installed in
 * `wp-content/languages/plugins` takes precedence in both cases.
 *
 * @return void
 */
function askai_button_load_textdomain() {
	load_plugin_textdomain( 'askai-button', false, dirname( plugin_basename( ASKAI_BUTTON_FILE ) ) . '/languages' );
}
add_action( 'init', 'askai_button_load_textdomain' );
