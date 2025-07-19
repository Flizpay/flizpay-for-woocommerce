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
 * Version:           2.4.10
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
define('FLIZPAY_VERSION', '2.4.10');

/**
 * Load Composer autoloader
 */
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Initialize Sentry error tracking after WordPress is loaded
 */
function flizpay_init_sentry() {
	/**
	 * Check for user consent for using telemetry (Sentry)
	 * This is used to collect anonymous usage and error data.
	 * If the user has not made a choice yet, we will save the default setting
	 * to 'yes' so that we can collect data.
	 * If the user has made a choice, we will respect that choice.
	 * The setting is stored in the WooCommerce settings array.
	 */
	$flizpay_settings = get_option('woocommerce_flizpay_settings', []);

	if (!isset($flizpay_settings['flizpay_sentry_enabled'])) {
		// If the setting does not exist, set it to 'yes' by default
		$flizpay_settings['flizpay_sentry_enabled'] = 'yes';
		update_option('woocommerce_flizpay_settings', $flizpay_settings);
	}

	/**
	 * Sentry error tracking integration.
	 * This integration is used to capture errors and performance data.
	 */
	\Sentry\init([
		'dsn' => 'https://d2941234a076cdd12190f707115ca5c9@o4507078336053248.ingest.de.sentry.io/4509638952419408',

		// Define how likely traces are sampled. Adjust this value in production, or use tracesSampler for greater control.
		'traces_sample_rate' => 1,

		// Decide whether to send certain events to Sentry or disable logging at all.
		'before_send' => static function (\Sentry\Event $event): ?\Sentry\Event {
			//  --------------------------------------------
			//  1) global switch living in the options table
			//  Check in WooCommerce settings array
			//  --------------------------------------------
			$flizpay_settings = get_option('woocommerce_flizpay_settings', []);
			$disabled = ($flizpay_settings['flizpay_sentry_enabled'] ?? '') !== 'yes';
			if ($disabled) {
				return null;
			}


			//  --------------------------------------------
			//  2) Per-event ignore flag
			//  --------------------------------------------
			$should_ignore_event = ($event->getExtra()['ignore_for_sentry'] ?? 'false') === 'true';
			if ($should_ignore_event) {
				return null;
			}

			//  --------------------------------------------
			//  3) Send only errors which originated from FLIZpay plugin
			//  --------------------------------------------
			$pluginPath = plugin_dir_path(__FILE__);


			//  --------------------------------------------
			// 	3.1) Look for a plugin frame in exceptions…
			//  --------------------------------------------
			foreach ($event->getExceptions() ?? [] as $exc) {
				if (! $stack = $exc->getStacktrace()) {
					continue;
				}
				foreach ($stack->getFrames() as $frame) {
					if (
						($file = $frame->getFile())
						&& strpos($file, $pluginPath) === 0
					) {
						// Found one: send it
						return $event;
					}
				}
			}

			//  --------------------------------------------
			// 	3.2) …and for "message" events (no exceptions), inspect the event's own stacktrace
			//  --------------------------------------------
			if ($stack = $event->getStacktrace()) {
				foreach ($stack->getFrames() as $frame) {
					if (
						($file = $frame->getFile())
						&& strpos($file, $pluginPath) === 0
					) {
						return $event;
					}
				}
			}

			//  --------------------------------------------
			//  No frames under our plugin dir → drop the event
			//  --------------------------------------------
			return null;
		}
	]);
}

// Hook Sentry initialization to plugins_loaded to ensure WordPress is ready
add_action('plugins_loaded', 'flizpay_init_sentry', 1);

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
	$plugin = plugin_basename(__FILE__);

	if ($options['action'] == 'update' && $options['type'] == 'plugin' && $options['plugins'][0] == $plugin) {
		require_once plugin_dir_path(__FILE__) . 'includes/class-flizpay-activator.php';
		Flizpay_Activator::activate();
	}
}

register_activation_hook(__FILE__, 'flizpay_activate');
register_deactivation_hook(__FILE__, 'flizpay_deactivate');
add_action('upgrader_process_complete', 'flizpay_upgrader');

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
