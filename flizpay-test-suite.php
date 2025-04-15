<?php
/**
 * FLIZpay for WooCommerce Test Suite
 *
 * @package     Flizpay
 * @subpackage  Tests
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * FLIZpay Test Suite
 */
class Flizpay_Test_Suite
{

    /**
     * Initialize the test suite
     */
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'setup_test_suite'), 20);
    }

    /**
     * Set up the test suite
     */
    public function setup_test_suite()
    {
        // Only run tests if explicitly requested
        if (!isset($_GET['run_flizpay_tests']) || !current_user_can('manage_options')) {
            return;
        }

        $test_type = isset($_GET['test_type']) ? sanitize_text_field($_GET['test_type']) : 'all';

        // Run the requested tests
        switch ($test_type) {
            case 'installation':
                $this->run_installation_tests();
                break;
            case 'admin':
                $this->run_admin_tests();
                break;
            case 'gateway':
                $this->run_gateway_tests();
                break;
            case 'webhook':
                $this->run_webhook_tests();
                break;
            case 'cashback':
                $this->run_cashback_tests();
                break;
            case 'express_checkout':
                $this->run_express_checkout_tests();
                break;
            case 'all':
            default:
                $this->run_all_tests();
                break;
        }
    }

    /**
     * Run all tests
     */
    private function run_all_tests()
    {
        $this->run_installation_tests();
        $this->run_admin_tests();
        $this->run_gateway_tests();
        $this->run_webhook_tests();
        $this->run_cashback_tests();
        $this->run_express_checkout_tests();
    }

    /**
     * Test plugin installation and dependencies
     */
    private function run_installation_tests()
    {
        $results = array(
            'title' => 'Installation Tests',
            'tests' => array()
        );

        // Test 1: WooCommerce dependency
        $test_woocommerce = array(
            'name' => 'WooCommerce Dependency Check',
            'description' => 'Verify WooCommerce is active',
            'status' => class_exists('WooCommerce') ? 'pass' : 'fail',
            'message' => class_exists('WooCommerce') ? 'WooCommerce is active' : 'WooCommerce is not active'
        );
        $results['tests'][] = $test_woocommerce;

        // Test 2: WooCommerce version
        $wc_version = class_exists('WooCommerce') ? WC()->version : '0.0.0';
        $required_version = '9.0.0';
        $test_wc_version = array(
            'name' => 'WooCommerce Version Check',
            'description' => 'Verify WooCommerce meets minimum version requirement',
            'status' => version_compare($wc_version, $required_version, '>=') ? 'pass' : 'fail',
            'message' => version_compare($wc_version, $required_version, '>=') ?
                "WooCommerce version $wc_version meets requirement ($required_version)" :
                "WooCommerce version $wc_version does not meet requirement ($required_version)"
        );
        $results['tests'][] = $test_wc_version;

        // Test 3: Plugin classes loaded
        $core_classes = array(
            'Flizpay',
            'Flizpay_Activator',
            'Flizpay_Deactivator',
            'WC_Flizpay_Gateway',
            'Flizpay_API_Service',
            'Flizpay_Webhook_Helper',
            'Flizpay_Cashback_Helper'
        );

        $missing_classes = array();
        foreach ($core_classes as $class) {
            if (!class_exists($class)) {
                $missing_classes[] = $class;
            }
        }

        $test_classes = array(
            'name' => 'Core Classes Check',
            'description' => 'Verify core plugin classes are loaded',
            'status' => empty($missing_classes) ? 'pass' : 'fail',
            'message' => empty($missing_classes) ?
                'All core classes loaded successfully' :
                'Missing core classes: ' . implode(', ', $missing_classes)
        );
        $results['tests'][] = $test_classes;

        // Test 4: Plugin settings exist
        $gateway_settings = get_option('woocommerce_flizpay_settings');
        $test_settings = array(
            'name' => 'Plugin Settings Check',
            'description' => 'Verify plugin settings exist',
            'status' => !empty($gateway_settings) ? 'pass' : 'fail',
            'message' => !empty($gateway_settings) ?
                'Plugin settings found' :
                'Plugin settings not found'
        );
        $results['tests'][] = $test_settings;

        // Test 5: Plugin version
        $declared_version = defined('FLIZPAY_VERSION') ? FLIZPAY_VERSION : 'undefined';
        $test_version = array(
            'name' => 'Plugin Version Check',
            'description' => 'Verify plugin version is defined',
            'status' => $declared_version !== 'undefined' ? 'pass' : 'fail',
            'message' => $declared_version !== 'undefined' ?
                "Plugin version: $declared_version" :
                'Plugin version is not defined'
        );
        $results['tests'][] = $test_version;

        $this->output_results($results);
    }

    /**
     * Test admin settings and configuration
     */
    private function run_admin_tests()
    {
        $results = array(
            'title' => 'Admin Configuration Tests',
            'tests' => array()
        );

        // Get gateway settings
        $settings = get_option('woocommerce_flizpay_settings');

        // Test 1: API Key Configuration
        $api_key = isset($settings['flizpay_api_key']) ? $settings['flizpay_api_key'] : '';
        $test_api_key = array(
            'name' => 'API Key Configuration',
            'description' => 'Verify API key is configured',
            'status' => !empty($api_key) ? 'pass' : 'warn',
            'message' => !empty($api_key) ?
                'API key is configured' :
                'API key is not configured (required for gateway to function)'
        );
        $results['tests'][] = $test_api_key;

        // Test 2: Webhook Configuration
        $webhook_key = isset($settings['flizpay_webhook_key']) ? $settings['flizpay_webhook_key'] : '';
        $webhook_url = isset($settings['flizpay_webhook_url']) ? $settings['flizpay_webhook_url'] : '';
        $webhook_alive = isset($settings['flizpay_webhook_alive']) ? $settings['flizpay_webhook_alive'] : 'no';

        $test_webhook = array(
            'name' => 'Webhook Configuration',
            'description' => 'Verify webhook is configured and connected',
            'status' => (!empty($webhook_key) && !empty($webhook_url) && $webhook_alive === 'yes') ? 'pass' : 'warn',
            'message' => (!empty($webhook_key) && !empty($webhook_url) && $webhook_alive === 'yes') ?
                'Webhook is properly configured and connected' :
                'Webhook is not fully configured (key, URL, or connection missing)'
        );
        $results['tests'][] = $test_webhook;

        // Test 3: Display Options
        $logo_enabled = isset($settings['flizpay_display_logo']) ? $settings['flizpay_display_logo'] : 'no';
        $desc_enabled = isset($settings['flizpay_display_description']) ? $settings['flizpay_display_description'] : 'no';
        $headline_enabled = isset($settings['flizpay_display_headline']) ? $settings['flizpay_display_headline'] : 'no';

        $display_options = array();
        if ($logo_enabled === 'yes')
            $display_options[] = 'Logo';
        if ($desc_enabled === 'yes')
            $display_options[] = 'Description';
        if ($headline_enabled === 'yes')
            $display_options[] = 'Headline';

        $test_display = array(
            'name' => 'Display Options Configuration',
            'description' => 'Check configured display options',
            'status' => 'info',
            'message' => !empty($display_options) ?
                'Enabled display options: ' . implode(', ', $display_options) :
                'No display options enabled'
        );
        $results['tests'][] = $test_display;

        // Test 4: Express Checkout Configuration
        $express_enabled = isset($settings['flizpay_enable_express_checkout']) ? $settings['flizpay_enable_express_checkout'] : 'no';
        $express_pages = isset($settings['flizpay_express_checkout_pages']) ? $settings['flizpay_express_checkout_pages'] : array();
        $express_theme = isset($settings['flizpay_express_checkout_theme']) ? $settings['flizpay_express_checkout_theme'] : 'light';

        $test_express = array(
            'name' => 'Express Checkout Configuration',
            'description' => 'Check express checkout configuration',
            'status' => 'info',
            'message' => $express_enabled === 'yes' ?
                'Express checkout enabled on pages: ' . (!empty($express_pages) ? implode(', ', $express_pages) : 'none') . ' (Theme: ' . $express_theme . ')' :
                'Express checkout disabled'
        );
        $results['tests'][] = $test_express;

        // Test 5: Gateway Settings Accessor
        if (class_exists('WC_Flizpay_Gateway')) {
            $payment_gateways = WC()->payment_gateways->payment_gateways();
            $gateway_instance = isset($payment_gateways['flizpay']) ? $payment_gateways['flizpay'] : null;

            $test_gateway = array(
                'name' => 'Gateway Instance Check',
                'description' => 'Verify gateway instance is accessible',
                'status' => !empty($gateway_instance) ? 'pass' : 'fail',
                'message' => !empty($gateway_instance) ?
                    'Gateway instance is accessible' :
                    'Gateway instance is not accessible'
            );
            $results['tests'][] = $test_gateway;
        }

        $this->output_results($results);
    }

    /**
     * Test payment gateway functionality
     */
    private function run_gateway_tests()
    {
        $results = array(
            'title' => 'Payment Gateway Tests',
            'tests' => array()
        );

        // Test 1: Gateway Registration
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $flizpay_registered = isset($available_gateways['flizpay']);

        $test_registration = array(
            'name' => 'Gateway Registration',
            'description' => 'Verify FLIZpay gateway is registered with WooCommerce',
            'status' => $flizpay_registered ? 'pass' : 'fail',
            'message' => $flizpay_registered ?
                'FLIZpay gateway is properly registered' :
                'FLIZpay gateway is not registered with WooCommerce'
        );
        $results['tests'][] = $test_registration;

        // Test 2: Gateway Settings
        $settings = get_option('woocommerce_flizpay_settings');
        $enabled = isset($settings['enabled']) ? $settings['enabled'] : 'no';

        $test_enabled = array(
            'name' => 'Gateway Status',
            'description' => 'Check if gateway is enabled',
            'status' => 'info',
            'message' => $enabled === 'yes' ?
                'Gateway is enabled for checkout' :
                'Gateway is not enabled for checkout'
        );
        $results['tests'][] = $test_enabled;

        // Test 3: Gateway Instance Methods
        if ($flizpay_registered) {
            $gateway = $available_gateways['flizpay'];

            // Check required methods
            $required_methods = array(
                'process_payment',
                'is_available'
            );

            $missing_methods = array();
            foreach ($required_methods as $method) {
                if (!method_exists($gateway, $method)) {
                    $missing_methods[] = $method;
                }
            }

            $test_methods = array(
                'name' => 'Required Methods Check',
                'description' => 'Verify all required gateway methods exist',
                'status' => empty($missing_methods) ? 'pass' : 'fail',
                'message' => empty($missing_methods) ?
                    'All required gateway methods exist' :
                    'Missing gateway methods: ' . implode(', ', $missing_methods)
            );
            $results['tests'][] = $test_methods;

            // Test 4: API Service Connection
            $api_key = isset($settings['flizpay_api_key']) ? $settings['flizpay_api_key'] : '';
            $api_service_exists = property_exists($gateway, 'api_service') && !empty($gateway->api_service);

            $test_api_service = array(
                'name' => 'API Service Connection',
                'description' => 'Verify API service is available',
                'status' => ($api_service_exists && !empty($api_key)) ? 'pass' : 'fail',
                'message' => ($api_service_exists && !empty($api_key)) ?
                    'API service is available and configured' :
                    'API service is not properly configured'
            );
            $results['tests'][] = $test_api_service;
        }

        // Test 5: Mock Order Creation
        $test_order_creation = array(
            'name' => 'Order Creation',
            'description' => 'Verify WooCommerce can create orders',
            'status' => function_exists('wc_create_order') ? 'pass' : 'fail',
            'message' => function_exists('wc_create_order') ?
                'WooCommerce order creation function is available' :
                'WooCommerce order creation function is not available'
        );
        $results['tests'][] = $test_order_creation;

        $this->output_results($results);
    }

    /**
     * Test webhook functionality
     */
    private function run_webhook_tests()
    {
        $results = array(
            'title' => 'Webhook Tests',
            'tests' => array()
        );

        // Test 1: Webhook Endpoint Registration
        $permalink_structure = get_option('permalink_structure');
        global $wp_rewrite;
        $rewrite_rules = $wp_rewrite->wp_rewrite_rules();
        $endpoint_exists = false;

        if (!empty($rewrite_rules)) {
            foreach ($rewrite_rules as $rule => $rewrite) {
                if (strpos($rule, 'flizpay-webhook') !== false) {
                    $endpoint_exists = true;
                    break;
                }
            }
        }

        $test_endpoint = array(
            'name' => 'Webhook Endpoint Registration',
            'description' => 'Verify webhook endpoint is registered',
            'status' => $endpoint_exists ? 'pass' : 'warn',
            'message' => $endpoint_exists ?
                'Webhook endpoint is registered in rewrite rules' :
                'Webhook endpoint may not be properly registered (try flushing permalinks)'
        );
        $results['tests'][] = $test_endpoint;

        // Test 2: Webhook Configuration
        $settings = get_option('woocommerce_flizpay_settings');
        $webhook_key = isset($settings['flizpay_webhook_key']) ? $settings['flizpay_webhook_key'] : '';
        $webhook_url = isset($settings['flizpay_webhook_url']) ? $settings['flizpay_webhook_url'] : '';

        $test_webhook_config = array(
            'name' => 'Webhook Configuration',
            'description' => 'Verify webhook key and URL are configured',
            'status' => (!empty($webhook_key) && !empty($webhook_url)) ? 'pass' : 'warn',
            'message' => (!empty($webhook_key) && !empty($webhook_url)) ?
                'Webhook key and URL are configured' :
                'Webhook key or URL is missing'
        );
        $results['tests'][] = $test_webhook_config;

        // Test 3: Webhook Helper Class
        $webhook_helper_exists = class_exists('Flizpay_Webhook_Helper');

        $test_webhook_helper = array(
            'name' => 'Webhook Helper Class',
            'description' => 'Verify webhook helper class is available',
            'status' => $webhook_helper_exists ? 'pass' : 'fail',
            'message' => $webhook_helper_exists ?
                'Webhook helper class is available' :
                'Webhook helper class is not available'
        );
        $results['tests'][] = $test_webhook_helper;

        // Test 4: Webhook Authentication Method
        $auth_method_exists = $webhook_helper_exists && method_exists('Flizpay_Webhook_Helper', 'webhook_authenticate');

        $test_auth_method = array(
            'name' => 'Webhook Authentication Method',
            'description' => 'Verify webhook authentication method exists',
            'status' => $auth_method_exists ? 'pass' : 'fail',
            'message' => $auth_method_exists ?
                'Webhook authentication method exists' :
                'Webhook authentication method is missing'
        );
        $results['tests'][] = $test_auth_method;

        // Test 5: Webhook URL Structure
        $site_url = get_site_url();
        $expected_url_pattern = $site_url . '/flizpay-webhook';
        $url_matches = !empty($webhook_url) && strpos($webhook_url, $expected_url_pattern) === 0;

        $test_url_structure = array(
            'name' => 'Webhook URL Structure',
            'description' => 'Verify webhook URL follows expected pattern',
            'status' => $url_matches ? 'pass' : 'warn',
            'message' => $url_matches ?
                'Webhook URL follows expected pattern' :
                'Webhook URL does not match expected pattern for this site'
        );
        $results['tests'][] = $test_url_structure;

        $this->output_results($results);
    }

    /**
     * Test cashback functionality
     */
    private function run_cashback_tests()
    {
        $results = array(
            'title' => 'Cashback Tests',
            'tests' => array()
        );

        // Test 1: Cashback Helper Class
        $cashback_helper_exists = class_exists('Flizpay_Cashback_Helper');

        $test_cashback_helper = array(
            'name' => 'Cashback Helper Class',
            'description' => 'Verify cashback helper class is available',
            'status' => $cashback_helper_exists ? 'pass' : 'fail',
            'message' => $cashback_helper_exists ?
                'Cashback helper class is available' :
                'Cashback helper class is not available'
        );
        $results['tests'][] = $test_cashback_helper;

        // Test 2: Cashback Configuration
        $settings = get_option('woocommerce_flizpay_settings');
        $cashback_data = isset($settings['flizpay_cashback']) ? $settings['flizpay_cashback'] : null;

        $test_cashback_config = array(
            'name' => 'Cashback Configuration',
            'description' => 'Check if cashback is configured',
            'status' => !empty($cashback_data) ? 'pass' : 'info',
            'message' => !empty($cashback_data) ?
                'Cashback is configured' :
                'Cashback is not configured'
        );
        $results['tests'][] = $test_cashback_config;

        // Test 3: Cashback Display Values
        if (!empty($cashback_data)) {
            $first_purchase = isset($cashback_data['first_purchase_amount']) ? floatval($cashback_data['first_purchase_amount']) : 0;
            $standard = isset($cashback_data['standard_amount']) ? floatval($cashback_data['standard_amount']) : 0;

            $cashback_type = '';
            if ($first_purchase > 0 && $standard > 0) {
                $cashback_type = 'both';
            } elseif ($first_purchase > 0) {
                $cashback_type = 'first purchase only';
            } elseif ($standard > 0) {
                $cashback_type = 'standard only';
            }

            $test_cashback_values = array(
                'name' => 'Cashback Values',
                'description' => 'Check configured cashback amounts',
                'status' => 'info',
                'message' => 'Cashback type: ' . $cashback_type .
                    ($first_purchase > 0 ? ' (First purchase: ' . $first_purchase . '%)' : '') .
                    ($standard > 0 ? ' (Standard: ' . $standard . '%)' : '')
            );
            $results['tests'][] = $test_cashback_values;
        }

        // Test 4: Cashback Display Methods
        if ($cashback_helper_exists) {
            $required_methods = array(
                'set_cashback_info',
                'set_title',
                'set_description'
            );

            $missing_methods = array();
            foreach ($required_methods as $method) {
                if (!method_exists('Flizpay_Cashback_Helper', $method)) {
                    $missing_methods[] = $method;
                }
            }

            $test_cashback_methods = array(
                'name' => 'Cashback Display Methods',
                'description' => 'Verify cashback display methods exist',
                'status' => empty($missing_methods) ? 'pass' : 'fail',
                'message' => empty($missing_methods) ?
                    'All cashback display methods exist' :
                    'Missing cashback methods: ' . implode(', ', $missing_methods)
            );
            $results['tests'][] = $test_cashback_methods;
        }

        // Test 5: Webhook Cashback Update Endpoint
        if (class_exists('Flizpay_Webhook_Helper') && method_exists('Flizpay_Webhook_Helper', 'handle_webhook_request')) {
            $reflection = new ReflectionClass('Flizpay_Webhook_Helper');
            $update_cashback_method_exists = $reflection->hasMethod('update_cashback_info');

            $test_cashback_update = array(
                'name' => 'Cashback Update Endpoint',
                'description' => 'Verify cashback update endpoint exists',
                'status' => $update_cashback_method_exists ? 'pass' : 'warn',
                'message' => $update_cashback_method_exists ?
                    'Cashback update endpoint is available' :
                    'Cashback update endpoint may be missing'
            );
            $results['tests'][] = $test_cashback_update;
        }

        $this->output_results($results);
    }

    /**
     * Test express checkout functionality
     */
    private function run_express_checkout_tests()
    {
        $results = array(
            'title' => 'Express Checkout Tests',
            'tests' => array()
        );

        // Test 1: Express Checkout Configuration
        $settings = get_option('woocommerce_flizpay_settings');
        $express_enabled = isset($settings['flizpay_enable_express_checkout']) ? $settings['flizpay_enable_express_checkout'] : 'no';

        $test_express_config = array(
            'name' => 'Express Checkout Configuration',
            'description' => 'Check if express checkout is enabled',
            'status' => $express_enabled === 'yes' ? 'pass' : 'info',
            'message' => $express_enabled === 'yes' ?
                'Express checkout is enabled' :
                'Express checkout is not enabled'
        );
        $results['tests'][] = $test_express_config;

        if ($express_enabled === 'yes') {
            // Test 2: Express Checkout Pages
            $express_pages = isset($settings['flizpay_express_checkout_pages']) ? $settings['flizpay_express_checkout_pages'] : array();

            $test_express_pages = array(
                'name' => 'Express Checkout Pages',
                'description' => 'Check configured express checkout pages',
                'status' => !empty($express_pages) ? 'pass' : 'warn',
                'message' => !empty($express_pages) ?
                    'Express checkout enabled on pages: ' . implode(', ', $express_pages) :
                    'Express checkout is enabled but no pages are configured'
            );
            $results['tests'][] = $test_express_pages;

            // Test 3: Express Checkout Scripts
            $scripts_registered = wp_script_is('flizpay-express-checkout', 'registered');

            $test_express_scripts = array(
                'name' => 'Express Checkout Scripts',
                'description' => 'Verify express checkout scripts are registered',
                'status' => $scripts_registered ? 'pass' : 'fail',
                'message' => $scripts_registered ?
                    'Express checkout scripts are registered' :
                    'Express checkout scripts are not registered'
            );
            $results['tests'][] = $test_express_scripts;

            // Test 4: AJAX Endpoint
            $ajax_endpoint_exists = function_exists('flizpay_express_checkout') ||
                (class_exists('WC_Flizpay_Gateway') && method_exists('WC_Flizpay_Gateway', 'flizpay_express_checkout'));

            $test_ajax_endpoint = array(
                'name' => 'AJAX Endpoint',
                'description' => 'Verify express checkout AJAX endpoint exists',
                'status' => $ajax_endpoint_exists ? 'pass' : 'fail',
                'message' => $ajax_endpoint_exists ?
                    'Express checkout AJAX endpoint exists' :
                    'Express checkout AJAX endpoint is missing'
            );
            $results['tests'][] = $test_ajax_endpoint;

            // Test 5: Express Checkout Assets
            $button_light = file_exists(plugin_dir_path(dirname(__FILE__)) . 'assets/images/fliz-express-checkout-logo-light.svg');
            $button_dark = file_exists(plugin_dir_path(dirname(__FILE__)) . 'assets/images/fliz-express-checkout-logo-dark.svg');

            $test_express_assets = array(
                'name' => 'Express Checkout Assets',
                'description' => 'Verify express checkout button assets exist',
                'status' => ($button_light && $button_dark) ? 'pass' : 'warn',
                'message' => ($button_light && $button_dark) ?
                    'Express checkout button assets exist' :
                    'Some express checkout button assets are missing'
            );
            $results['tests'][] = $test_express_assets;
        }

        $this->output_results($results);
    }

    /**
     * Output test results in a formatted table
     *
     * @param array $results Test results array
     */
    private function output_results($results)
    {
        echo '<div class="flizpay-test-results">';
        echo '<h2>' . esc_html($results['title']) . '</h2>';
        echo '<table class="widefat" style="margin-bottom: 20px;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Test</th>';
        echo '<th>Description</th>';
        echo '<th>Status</th>';
        echo '<th>Details</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($results['tests'] as $test) {
            $status_class = '';
            $status_label = '';

            switch ($test['status']) {
                case 'pass':
                    $status_class = 'updated';
                    $status_label = 'PASS';
                    break;
                case 'fail':
                    $status_class = 'error';
                    $status_label = 'FAIL';
                    break;
                case 'warn':
                    $status_class = 'warning';
                    $status_label = 'WARNING';
                    break;
                case 'info':
                default:
                    $status_class = 'info';
                    $status_label = 'INFO';
                    break;
            }

            echo '<tr>';
            echo '<td><strong>' . esc_html($test['name']) . '</strong></td>';
            echo '<td>' . esc_html($test['description']) . '</td>';
            echo '<td><span class="flizpay-test-status ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span></td>';
            echo '<td>' . esc_html($test['message']) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}

// Initialize test suite
$flizpay_test_suite = new Flizpay_Test_Suite();

/**
 * Admin notice to run tests
 */
add_action('admin_notices', 'flizpay_test_suite_notice');
function flizpay_test_suite_notice()
{
    // Only show on WooCommerce pages
    if (!function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, array('woocommerce_page_wc-settings', 'plugins'))) {
        return;
    }

    // Only show for admins
    if (!current_user_can('manage_options')) {
        return;
    }

    $run_tests_url = add_query_arg(array(
        'run_flizpay_tests' => 1,
        'test_type' => 'all'
    ));

    ?>
    <div class="notice notice-info">
        <p>
            <strong>FLIZpay Test Suite</strong>:
            <a href="<?php echo esc_url($run_tests_url); ?>" class="button">Run All Tests</a>
            <a href="<?php echo esc_url(add_query_arg(array('run_flizpay_tests' => 1, 'test_type' => 'installation'))); ?>"
                class="button">Installation Tests</a>
            <a href="<?php echo esc_url(add_query_arg(array('run_flizpay_tests' => 1, 'test_type' => 'admin'))); ?>"
                class="button">Admin Tests</a>
            <a href="<?php echo esc_url(add_query_arg(array('run_flizpay_tests' => 1, 'test_type' => 'gateway'))); ?>"
                class="button">Gateway Tests</a>
            <a href="<?php echo esc_url(add_query_arg(array('run_flizpay_tests' => 1, 'test_type' => 'webhook'))); ?>"
                class="button">Webhook Tests</a>
            <a href="<?php echo esc_url(add_query_arg(array('run_flizpay_tests' => 1, 'test_type' => 'cashback'))); ?>"
                class="button">Cashback Tests</a>
            <a href="<?php echo esc_url(add_query_arg(array('run_flizpay_tests' => 1, 'test_type' => 'express_checkout'))); ?>"
                class="button">Express Checkout Tests</a>
        </p>
    </div>
    <?php
}

/**
 * Add CSS for test results
 */
add_action('admin_head', 'flizpay_test_suite_css');
function flizpay_test_suite_css()
{
    ?>
    <style>
        .flizpay-test-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-weight: bold;
        }

        .flizpay-test-status.updated {
            background-color: #d4edda;
            color: #155724;
        }

        .flizpay-test-status.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .flizpay-test-status.warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .flizpay-test-status.info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
    </style>
    <?php
}