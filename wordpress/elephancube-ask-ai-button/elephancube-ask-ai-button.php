<?php
/**
 * Plugin Name:       Elephancube Ask AI Button
 * Plugin URI:        https://github.com/elephancube/askai-button
 * Description:       Adds a floating "Ask AI" button. The visitor's question and your site URL are handed to ChatGPT, Claude or Google AI Mode in a new tab. No API key, no external requests by the plugin, and the question is never stored.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Elephancube Inc.
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elephancube-ask-ai-button
 * Domain Path:       /languages
 *
 * @package Askai_Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * The plugin was first distributed on GitHub in the folder `askai-button`.
 * That copy declares the same classes, functions and constants, so the two
 * cannot run at the same time. WordPress loads that folder first, so when it
 * is active this copy loads nothing, and says so on the admin screens.
 */
if ( defined( 'ASKAI_BUTTON_VERSION' ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Elephancube Ask AI Button is not running: an earlier copy of this plugin, in the folder askai-button, is active. Deactivate that copy on the Plugins screen. Do not delete it there: deleting it also deletes the settings and usage counts that this plugin uses.', 'elephancube-ask-ai-button' ) . '</p></div>';
		}
	);
	return;
}

define( 'ASKAI_BUTTON_VERSION', '0.1.0' );
define( 'ASKAI_BUTTON_FILE', __FILE__ );
define( 'ASKAI_BUTTON_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASKAI_BUTTON_URL', plugin_dir_url( __FILE__ ) );

require_once ASKAI_BUTTON_DIR . 'includes/plugin.php';
