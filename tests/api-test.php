<?php
/**
 * FLIZpay API Tests
 *
 * @package     Flizpay
 * @subpackage  Tests
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * FLIZpay API Test Suite
 * 
 * This class provides tests for the FLIZpay API integration.
 * Run with WP CLI: wp eval-file api-test.php
 */
class Flizpay_API_Test
{

    private $api_key;
    private $api_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_dependencies();
        $this->setup_api();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies()
    {
        if (!class_exists('WC_Flizpay_Gateway')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-flizpay-gateway.php';
        }

        if (!class_exists('Flizpay_API_Service')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-flizpay-api-service.php';
        }
    }

    /**
     * Set up API service with merchant API key
     */
    private function setup_api()
    {
        // Get API key from gateway settings
        $settings = get_option('woocommerce_flizpay_settings');
        $this->api_key = isset($settings['flizpay_api_key']) ? $settings['flizpay_api_key'] : '';

        if (empty($this->api_key)) {
            $this->output_error('API key not found in settings. Please configure the FLIZpay gateway first.');
            return;
        }

        $this->api_service = new Flizpay_API_Service($this->api_key);
    }

    /**
     * Run all API tests
     */
    public function run_tests()
    {
        $this->output_header('FLIZpay API Integration Tests');

        if (empty($this->api_key) || empty($this->api_service)) {
            return;
        }

        $this->test_webhook_generation();
        $this->test_cashback_fetch();
        $this->test_transaction_simulation();
    }

    /**
     * Test webhook generation endpoint
     */
    private function test_webhook_generation()
    {
        $this->output_test_header('Webhook Generation Test');

        $this->output_info('Testing webhook URL generation...');

        try {
            $webhook_url = $this->api_service->generate_webhook_url();

            if (!empty($webhook_url)) {
                $this->output_success('Successfully generated webhook URL: ' . $webhook_url);
                $webhook_key = $this->api_service->get_webhook_key();

                if (!empty($webhook_key)) {
                    $this->output_success('Successfully retrieved webhook key');
                } else {
                    $this->output_error('Failed to retrieve webhook key');
                }
            } else {
                $this->output_error('Failed to generate webhook URL');
            }
        } catch (Exception $e) {
            $this->output_error('Exception occurred: ' . $e->getMessage());
        }
    }

    /**
     * Test cashback data fetching
     */
    private function test_cashback_fetch()
    {
        $this->output_test_header('Cashback Data Test');

        $this->output_info('Testing cashback data retrieval...');

        try {
            $cashback_data = $this->api_service->fetch_cashback_data();

            if (!empty($cashback_data)) {
                $this->output_success('Successfully retrieved cashback data:');
                $this->output_info('First Purchase Amount: ' .
                    (isset($cashback_data['first_purchase_amount']) ? $cashback_data['first_purchase_amount'] : 'Not set'));
                $this->output_info('Standard Amount: ' .
                    (isset($cashback_data['standard_amount']) ? $cashback_data['standard_amount'] : 'Not set'));
            } else {
                $this->output_warning('No cashback data available for this merchant');
            }
        } catch (Exception $e) {
            $this->output_error('Exception occurred: ' . $e->getMessage());
        }
    }

    /**
     * Test transaction creation with a simulated order
     */
    private function test_transaction_simulation()
    {
        $this->output_test_header('Transaction Simulation Test');

        // Only run if WooCommerce is active
        if (!class_exists('WC_Order')) {
            $this->output_error('WooCommerce classes not available. Test skipped.');
            return;
        }

        $this->output_info('Creating test order...');

        // Create a test order
        $order = new WC_Order();
        $order->set_status('pending');
        $order->set_payment_method('flizpay');
        $order->set_total(50.00);
        $order->set_billing_email('test@example.com');
        $order->set_billing_first_name('Test');
        $order->set_billing_last_name('User');
        $order->save();

        $this->output_success('Test order created with ID: ' . $order->get_id());
        $this->output_info('Testing transaction creation for test order...');

        try {
            // Set the test flag to prevent actual API call
            //$this->api_service->enable_test_mode();

            $transaction_url = $this->api_service->create_transaction($order, 'test');

            if (!empty($transaction_url)) {
                $this->output_success('Successfully generated transaction URL (test mode): ' . $transaction_url);
            } else {
                $this->output_error('Failed to generate transaction URL');
            }
        } catch (Exception $e) {
            $this->output_error('Exception occurred: ' . $e->getMessage());
        }

        // Clean up test order
        $this->output_info('Cleaning up test order...');
        $order->delete(true);
        $this->output_success('Test order deleted');
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
$api_tests = new Flizpay_API_Test();
$api_tests->run_tests();