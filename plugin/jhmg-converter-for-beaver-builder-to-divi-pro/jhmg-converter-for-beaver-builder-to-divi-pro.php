<?php
/**
 * Plugin Name:       JHMG Converter For Beaver Builder to Divi 5 — Pro
 * Description:       Pro add-on: convert many pages per run, and send Beaver Themer headers and footers to the Divi Theme Builder.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * Requires Plugins:  jhmg-converter-for-beaver-builder-to-divi
 * Author:            Lucas Lopvet
 * License:           GPLv2 or later
 * Text Domain:       jhmg-converter-for-beaver-builder-to-divi-pro
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'BDCP_PLUGIN_FILE', __FILE__ );
define( 'BDCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BDCP_PLUGIN_VERSION', '1.0.0' );
define( 'BDCP_PRODUCT_SLUG', 'beaver-to-divi5-pro' );
// Overridable for local/dev licence servers: define BDCP_API_BASE in wp-config.php.
defined( 'BDCP_API_BASE' ) || define( 'BDCP_API_BASE', 'https://divi5lab.com' );

require_once BDCP_PLUGIN_DIR . 'includes/class-autoloader.php';

\BeaverDivi5Converter\Pro\Plugin::instance()->init();
