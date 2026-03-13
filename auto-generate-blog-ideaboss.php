<?php
/**
 * Plugin Name: Auto Generate Blog by ideaBoss
 * Plugin URI:  https://ideaboss.io
 * Description: Automate blog post creation using Claude AI. Generate posts from prompts or import and format articles from URLs — with SEO, attribution, and Yoast integration built in.
 * Version:     1.0.4
 * Author:      ideaBoss
 * Author URI:  https://ideaboss.io
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-generate-blog-ideaboss
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGB_VERSION',         '1.0.4' );
define( 'AGB_PLUGIN_DIR',      plugin_dir_path( __FILE__ ) );
define( 'AGB_PLUGIN_URL',      plugin_dir_url( __FILE__ ) );
define( 'AGB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once AGB_PLUGIN_DIR . 'includes/class-agb-settings.php';
require_once AGB_PLUGIN_DIR . 'includes/class-agb-claude-api.php';
require_once AGB_PLUGIN_DIR . 'includes/class-agb-article-fetcher.php';
require_once AGB_PLUGIN_DIR . 'includes/class-agb-post-generator.php';
require_once AGB_PLUGIN_DIR . 'includes/class-agb-auto-updater.php';

function agb_init() {
	new AGB_Settings();
	new AGB_Post_Generator();
	new AGB_Auto_Updater();
}
add_action( 'plugins_loaded', 'agb_init' );
