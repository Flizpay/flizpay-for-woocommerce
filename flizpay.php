<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.flizpay.de
 * @since             1.0.0
 * @package           Flizpay
 *
 * @wordpress-plugin
 * Plugin Name:       FLIZpay Gateway für WooCommerce
 * Plugin URI:        https://www.flizpay.de/companies
 * Description:       FLIZpay: 100% free!
 * Version:           2.5.1
 * Author:            FLIZpay
 * Author URI:        https://www.flizpay.de/companies
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       flizpay-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 4.4
 * Tested up to:      7.0
 * Requires PHP:      7.0
 * Requires Plugins: 	woocommerce
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define('FLIZPAY_VERSION', '2.5.1');

/**
 * Load Composer autoloader only if PHP version meets requirements
 */
if (file_exists(__DIR__ . '/vendor/autoload.php') && version_compare(PHP_VERSION, '8.2.0', '>=')) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Load Sentry helper class
 */
require_once plugin_dir_path(__FILE__) . 'includes/class-flizpay-sentry.php';

/**
 * Initialize Sentry after WordPress is loaded
 */
add_action('plugins_loaded', array('Flizpay_Sentry', 'init'), 1);

function flizpay_check_dependencies()
{
	// Check if WooCommerce is active
	if (!class_exists('WooCommerce')) {
		// WooCommerce is not active
		add_action('admin_notices', 'flizpay_dependencies_notice');
		deactivate_plugins(plugin_basename(__FILE__)); // Deactivate the plugin
	}

	global $woocommerce;

	// Define the minimum required WooCommerce version
	$required_version = '9.0.0';

	// Check WooCommerce version
	if (version_compare($woocommerce->version, $required_version, '<')) {
		add_action('admin_notices', 'flizpay_version_notice');
		deactivate_plugins(plugin_basename(__FILE__));
	}
}

add_action('admin_init', 'flizpay_check_dependencies');

function flizpay_dependencies_notice()
{
	echo '<div class="error"><p><strong>FLIZpay</strong> requires WooCommerce to be installed and active. Please install and activate WooCommerce.</p></div>';
}

function flizpay_version_notice()
{
	echo '<div class="error"><p><strong>FLIZpay</strong> requires WooCommerce version 9.0.0 or higher. Please update WooCommerce.</p></div>';
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-flizpay-activator.php
 */
function flizpay_activate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-flizpay-activator.php';
	Flizpay_Activator::activate();
	flizpay_check_dependencies();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-flizpay-deactivator.php
 */
function flizpay_deactivate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-flizpay-deactivator.php';
	Flizpay_Deactivator::deactivate();
}

function flizpay_upgrader($upgrader, $options = null)
{
	if (! is_array($options) || empty($options['plugins']) || ! is_array($options['plugins'])) {
		return;
	}

	$plugin = plugin_basename(__FILE__);

	if (($options['action'] ?? '') === 'update' && ($options['type'] ?? '') === 'plugin' && in_array($plugin, $options['plugins'], true)) {
		update_option('flizpay_plugin_version_sync_needed', true);
	}
}

function flizpay_sync_plugin_version_if_needed()
{
	if (!get_option('flizpay_plugin_version_sync_needed')) {
		return;
	}

	if (get_transient('flizpay_plugin_version_sync_failed')) {
		return;
	}

	$flizpay_settings = get_option('woocommerce_flizpay_settings');

	if (!is_array($flizpay_settings) || empty($flizpay_settings['flizpay_api_key'])) {
		return;
	}

	require_once plugin_dir_path(__FILE__) . 'includes/class-flizpay-api.php';

	try {
		$api_client = WC_Flizpay_API::get_instance($flizpay_settings['flizpay_api_key']);
		$response = $api_client->dispatch('edit_business', array('pluginVersion' => FLIZPAY_VERSION), false);

		if (is_array($response) && ($response['pluginVersion'] ?? null) === FLIZPAY_VERSION) {
			update_option('flizpay_reported_plugin_version', FLIZPAY_VERSION);
			delete_option('flizpay_plugin_version_sync_needed');
			delete_transient('flizpay_plugin_version_sync_failed');
			return;
		}
	} catch (Throwable $e) {
		// Retry later without blocking the admin request.
	}

	set_transient('flizpay_plugin_version_sync_failed', true, 3600);
}

register_activation_hook(__FILE__, 'flizpay_activate');
register_deactivation_hook(__FILE__, 'flizpay_deactivate');
add_action('upgrader_process_complete', 'flizpay_upgrader');
add_action('init', 'flizpay_sync_plugin_version_if_needed', 20);

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-flizpay.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function flizpay_run()
{

	$plugin = new Flizpay();
	$plugin->run();
}
flizpay_run();

/**
 * This action hook registers Flizpay class as a WooCommerce payment gateway
 * We can use unshift to reorder it as first
 */
add_filter('woocommerce_payment_gateways', 'flizpay_add_gateway_class');
function flizpay_add_gateway_class($gateways)
{
	array_unshift($gateways, 'WC_Flizpay_Gateway');
	return $gateways;
}
