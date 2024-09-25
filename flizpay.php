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
 * Plugin Name:       FLIZpay
 * Plugin URI:        https://www.flizpay.de/companies
 * Description:       FLIZpay: 100% free!
 * Version:           1.0.2
 * Author:            FLIZpay
 * Author URI:        https://www.flizpay.de/companies
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       flizpay-for-woocommerce
 * Domain Path:       /languages
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
define('FLIZPAY_VERSION', '1.0.2');

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

register_activation_hook(__FILE__, 'flizpay_activate');
register_deactivation_hook(__FILE__, 'flizpay_deactivate');

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
