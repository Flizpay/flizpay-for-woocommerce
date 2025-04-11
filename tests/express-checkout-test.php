<?php
/**
 * FLIZpay Express Checkout Tests
 *
 * @package     Flizpay
 * @subpackage  Tests
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * FLIZpay Express Checkout Test Suite
 * 
 * This class provides tests for the FLIZpay express checkout functionality.
 */
class Flizpay_Express_Checkout_Test
{

    private $gateway;
    private $script_registration;
    private $display_configuration;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->setup_gateway();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies()
    {
        if (!class_exists('WC_Flizpay_Gateway')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-flizpay-gateway.php';
        }
    }

    /**
     * Set up gateway instance
     */
    private function setup_gateway()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            $this->output_error('WooCommerce payment gateway class not found. Is WooCommerce activated?');
            return;
        }

        // Create a gateway instance
        $this->gateway = new WC_Flizpay_Gateway();
    }

    /**
     * Run all express checkout tests
     */
    public function run_tests()
    {
        $this->output_header('FLIZpay Express Checkout Tests');

        if (empty($this->gateway)) {
            return;
        }

        $this->check_express_checkout_configuration();
        $this->check_script_registration();
        $this->check_button_display_locations();
        $this->check_ajax_endpoint();
        $this->check_button_assets();
    }

    /**
     * Check express checkout configuration
     */
    private function check_express_checkout_configuration()
    {
        $this->output_test_header('Express Checkout Configuration');

        $settings = get_option('woocommerce_flizpay_settings');
        $express_enabled = isset($settings['flizpay_enable_express_checkout']) ? $settings['flizpay_enable_express_checkout'] : 'no';
        $express_pages = isset($settings['flizpay_express_checkout_pages']) ? $settings['flizpay_express_checkout_pages'] : array();
        $express_theme = isset($settings['flizpay_express_checkout_theme']) ? $settings['flizpay_express_checkout_theme'] : 'light';

        $this->output_info('Express checkout enabled: ' . ($express_enabled === 'yes' ? 'Yes' : 'No'));

        if ($express_enabled === 'yes') {
            $this->output_success('Express checkout is configured');
            $this->display_configuration = array(
                'enabled' => true,
                'pages' => $express_pages,
                'theme' => $express_theme
            );

            if (!empty($express_pages)) {
                $this->output_info('Configured pages: ' . implode(', ', $express_pages));
            } else {
                $this->output_warning('No pages configured for express checkout display');
            }

            $this->output_info('Button theme: ' . $express_theme);
        } else {
            $this->output_info('Express checkout is not enabled');
            $this->display_configuration = array(
                'enabled' => false
            );
        }
    }

    /**
     * Check script registration
     */
    private function check_script_registration()
    {
        $this->output_test_header('Script Registration');

        $scripts_registered = false;

        // Check if Flizpay_Public class exists
        if (class_exists('Flizpay_Public')) {
            $this->output_info('Flizpay_Public class exists');

            // Instantiate public class
            $public = new Flizpay_Public('flizpay-for-woocommerce', FLIZPAY_VERSION);
            if (method_exists($public, 'enqueue_scripts')) {
                $this->output_info('enqueue_scripts method exists');

                // Trigger script registration
                $public->enqueue_scripts();

                // Check if script is registered
                $scripts_registered = wp_script_is('flizpay-express-checkout', 'registered');
            }
        }

        if ($scripts_registered) {
            $this->output_success('Express checkout scripts are properly registered');
            $this->script_registration = true;
        } else {
            $this->output_error('Express checkout scripts are not properly registered');
            $this->script_registration = false;
        }

        $nonce_exists = wp_verify_nonce('express_checkout_nonce') !== false;
        $this->output_info('Express checkout nonce creation: ' . ($nonce_exists ? 'Working' : 'Not working'));
    }

    /**
     * Check button display locations
     */
    private function check_button_display_locations()
    {
        $this->output_test_header('Button Display Locations');

        if (empty($this->display_configuration) || !$this->display_configuration['enabled']) {
            $this->output_info('Express checkout is not enabled, skipping location tests');
            return;
        }

        $locations = array(
            'cart' => array(
                'hook' => 'woocommerce_proceed_to_checkout',
                'enabled' => in_array('cart', $this->display_configuration['pages'])
            ),
            'product' => array(
                'hook' => 'woocommerce_after_add_to_cart_button',
                'enabled' => in_array('product', $this->display_configuration['pages'])
            ),
            'checkout' => array(
                'hook' => 'woocommerce_before_checkout_form',
                'enabled' => in_array('checkout', $this->display_configuration['pages'])
            )
        );

        foreach ($locations as $page => $location) {
            $this->output_info($page . ' page: ' . ($location['enabled'] ? 'Enabled' : 'Disabled'));

            if ($location['enabled']) {
                $has_filter = has_action($location['hook']);
                $this->output_info($page . ' page hook status: ' . ($has_filter ? 'Has callbacks' : 'No callbacks'));
            }
        }

        $theme = $this->display_configuration['theme'];
        $expected_button_url = 'fliz-express-checkout-logo-' . $theme . '.svg';
        $this->output_info('Expected button image: ' . $expected_button_url);
    }

    /**
     * Check AJAX endpoint
     */
    private function check_ajax_endpoint()
    {
        $this->output_test_header('AJAX Endpoint');

        // Check if the gateway has the express checkout method
        $has_method = method_exists($this->gateway, 'flizpay_express_checkout');
        $proper_ajax = has_action('wp_ajax_flizpay_express_checkout') && has_action('wp_ajax_nopriv_flizpay_express_checkout');

        if ($has_method) {
            $this->output_success('flizpay_express_checkout method exists in gateway class');
        } else {
            $this->output_error('flizpay_express_checkout method missing from gateway class');
        }

        if ($proper_ajax) {
            $this->output_success('AJAX actions are properly registered');
        } else {
            $this->output_error('AJAX actions are not properly registered');
        }

        $this->output_info('Expected AJAX URL: ' . admin_url('admin-ajax.php?action=flizpay_express_checkout'));
    }

    /**
     * Check button assets
     */
    private function check_button_assets()
    {
        $this->output_test_header('Button Assets');

        $assets_path = dirname(dirname(__FILE__)) . '/assets/images/';
        $light_button = $assets_path . 'fliz-express-checkout-logo-light.svg';
        $dark_button = $assets_path . 'fliz-express-checkout-logo-dark.svg';

        $light_exists = file_exists($light_button);
        $dark_exists = file_exists($dark_button);

        if ($light_exists) {
            $this->output_success('Light theme button asset exists');
        } else {
            $this->output_error('Light theme button asset is missing');
        }

        if ($dark_exists) {
            $this->output_success('Dark theme button asset exists');
        } else {
            $this->output_error('Dark theme button asset is missing');
        }

        // Check example images
        $example_images = array(
            'light-de' => 'fliz-express-checkout-example-light-de.png',
            'light-en' => 'fliz-express-checkout-example-light-en.png',
            'dark-de' => 'fliz-express-checkout-example-dark-de.png',
            'dark-en' => 'fliz-express-checkout-example-dark-en.png'
        );

        $missing_examples = array();
        foreach ($example_images as $key => $image) {
            if (!file_exists($assets_path . $image)) {
                $missing_examples[] = $key;
            }
        }

        if (empty($missing_examples)) {
            $this->output_success('All example images exist');
        } else {
            $this->output_warning('Some example images are missing: ' . implode(', ', $missing_examples));
        }
    }

    /**
     * Output formatted header
     */
    private function output_header($text)
    {
        echo "\n";
        echo "===================================================================\n";
        echo " $text \n";
        echo "===================================================================\n";
    }

    /**
     * Output test section header
     */
    private function output_test_header($text)
    {
        echo "\n";
        echo "-------------------------------------------------------------------\n";
        echo " $text \n";
        echo "-------------------------------------------------------------------\n";
    }

    /**
     * Output success message
     */
    private function output_success($text)
    {
        echo "\033[32m✓ $text\033[0m\n";
    }

    /**
     * Output error message
     */
    private function output_error($text)
    {
        echo "\033[31m✗ $text\033[0m\n";
    }

    /**
     * Output warning message
     */
    private function output_warning($text)
    {
        echo "\033[33m! $text\033[0m\n";
    }

    /**
     * Output info message
     */
    private function output_info($text)
    {
        echo "\033[36m• $text\033[0m\n";
    }
}

// Instantiate and run the tests
$express_checkout_tests = new Flizpay_Express_Checkout_Test();
$express_checkout_tests->run_tests();