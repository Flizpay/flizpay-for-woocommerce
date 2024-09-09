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
 * Plugin Name:       Flizpay
 * Plugin URI:        https://www.flizpay.de
 * Description:       FLIZpay: 100% free!
 * Version:           1.0.0
 * Author:            Flizpay
 * Author URI:        https://www.flizpay.de/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       flizpay-for-woocommerce
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 */
define('FLIZPAY_VERSION', '1.0.0');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-flizpay-activator.php
 */
function flizpay_activate()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-flizpay-activator.php';
	Flizpay_Activator::activate();
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
