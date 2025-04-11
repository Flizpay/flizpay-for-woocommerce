<?php
/**
 * FLIZpay Webhook Tests
 *
 * @package     Flizpay
 * @subpackage  Tests
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * FLIZpay Webhook Test Suite
 * 
 * This class provides tests for the FLIZpay webhook handling functionality.
 * It simulates webhook payloads to test order processing.
 */
class Flizpay_Webhook_Test {
    
    private $webhook_helper;
    private $gateway;
    private $webhook_key;
    private $test_order_id;
    private $simulate_only = true; // Set to false to actually process webhooks
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_dependencies();
        $this->setup_gateway();
        $this->initialize_webhook_helper();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        if (!class_exists('WC_Flizpay_Gateway')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-flizpay-gateway.php';
        }
        
        if (!class_exists('Flizpay_Webhook_Helper')) {
            require_once dirname(dirname(__FILE__)) . '/includes/class-flizpay-webhook-helper.php';
        }
    }
    
    /**
     * Set up gateway instance
     */
    private function setup_gateway() {
        if (!class_exists('WC_Payment_Gateway')) {
            $this->output_error('WooCommerce payment gateway class not found. Is WooCommerce activated?');
            return;
        }
        
        // Get gateway settings
        $settings = get_option('woocommerce_flizpay_settings');
        $this->webhook_key = isset($settings['flizpay_webhook_key']) ? $settings['flizpay_webhook_key'] : '';
        
        if (empty($this->webhook_key)) {
            $this->output_error('Webhook key not found in settings. Please configure the FLIZpay gateway first.');
            return;
        }
        
        // Create a gateway instance
        $this->gateway = new WC_Flizpay_Gateway();
    }
    
    /**
     * Initialize webhook helper
     */
    private function initialize_webhook_helper() {
        if (!$this->gateway) {
            return;
        }
        
        $this->webhook_helper = new Flizpay_Webhook_Helper($this->gateway);
    }
    
    /**
     * Run all webhook tests
     */
    public function run_tests() {
        $this->output_header('FLIZpay Webhook Tests');
        
        if (empty($this->webhook_helper)) {
            return;
        }
        
        $this->create_test_order();
        if ($this->test_order_id) {
            $this->test_transaction_complete();
            $this->test_shipping_info();
            $this->test_shipping_method();
            $this->test_cashback_update();
            $this->test_connection();
            $this->cleanup_test_order();
        }
    }
    
    /**
     * Create a test order for webhook testing
     */
    private function create_test_order() {
        $this->output_test_header('Test Order Creation');
        
        if (!class_exists('WC_Order')) {
            $this->output_error('WooCommerce order class not found. Is WooCommerce activated?');
            return;
        }
        
        $this->output_info('Creating test order...');
        
        // Create a test order
        $order = new WC_Order();
        $order->set_status('pending');
        $order->set_payment_method('flizpay');
        $order->set_total(100.00);
        $order->set_subtotal(80.00);
        $order->set_shipping_total(20.00);
        $order->set_billing_email('test@example.com');
        $order->set_billing_first_name('Test');
        $order->set_billing_last_name('User');
        
        // Add a product line item
        $item = new WC_Order_Item_Product();
        $item->set_props(array(
            'name' => 'Test Product',
            'quantity' => 1,
            'subtotal' => 80.00,
            'total' => 80.00,
        ));
        $order->add_item($item);
        
        // Add a shipping line item
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_props(array(
            'method_title' => 'Flat rate',
            'method_id' => 'flat_rate',
            'total' => 20.00
        ));
        $order->add_item($shipping);
        
        $order->calculate_totals();
        $order->save();
        
        $this->test_order_id = $order->get_id();
        $this->output_success('Test order created with ID: ' . $this->test_order_id);
    }
    
    /**
     * Test completed transaction webhook
     */
    private function test_transaction_complete() {
        $this->output_test_header('Transaction Complete Webhook Test');
        
        $this->output_info('Testing transaction complete webhook...');
        
        $payload = array(
            'status' => 'completed',
            'transactionId' => 'test_' . uniqid(),
            'originalAmount' => 100.00,
            'amount' => 90.00, // Simulating 10% cashback
            'currency' => 'EUR',
            'metadata' => array(
                'orderId' => $this->test_order_id
            )
        );
        
        // Add signature
        $signature = hash_hmac('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE), $this->webhook_key);
        
        $this->output_info('Generated payload for transaction complete webhook');
        $this->output_info('Transaction ID: ' . $payload['transactionId']);
        $this->output_info('Original Amount: ' . $payload['originalAmount']);
        $this->output_info('Final Amount: ' . $payload['amount'] . ' (simulating cashback)');
        
        if ($this->simulate_only) {
            $this->output_warning('Simulation mode: Not actually processing webhook');
        } else {
            $this->output_info('Processing webhook...');
            $_SERVER['HTTP_X_FLIZ_SIGNATURE'] = $signature;
            $this->webhook_helper->finish_order($payload);
            $this->output_success('Webhook processed');
            
            // Verify order status
            $order = wc_get_order($this->test_order_id);
            $this->output_info('Order status: ' . $order->get_status());
            $this->output_info('Order total after cashback: ' . $order->get_total());
        }
    }
    
    /**
     * Test shipping info webhook
     */
    private function test_shipping_info() {
        $this->output_test_header('Shipping Info Webhook Test');
        
        $this->output_info('Testing shipping info webhook...');
        
        $payload = array(
            'shippingInfo' => true,
            'transactionId' => 'test_' . uniqid(),
            'orderId' => $this->test_order_id,
            'address' => array(
                'firstName' => 'John',
                'lastName' => 'Doe',
                'street' => 'Test Street 123',
                'city' => 'Test City',
                'postalCode' => '12345',
                'country' => 'DE'
            )
        );
        
        // Add signature
        $signature = hash_hmac('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE), $this->webhook_key);
        
        $this->output_info('Generated payload for shipping info webhook');
        
        if ($this->simulate_only) {
            $this->output_warning('Simulation mode: Not actually processing webhook');
        } else {
            $this->output_info('Processing webhook...');
            $_SERVER['HTTP_X_FLIZ_SIGNATURE'] = $signature;
            $result = $this->gateway->calculate_shipping($payload);
            $this->output_success('Webhook processed');
            $this->output_info('Shipping methods returned: ' . count($result['methods']));
        }
    }
    
    /**
     * Test shipping method webhook
     */
    private function test_shipping_method() {
        $this->output_test_header('Shipping Method Webhook Test');
        
        $this->output_info('Testing shipping method selection webhook...');
        
        $payload = array(
            'shippingMethodId' => 'flat_rate',
            'transactionId' => 'test_' . uniqid(),
            'orderId' => $this->test_order_id
        );
        
        // Add signature
        $signature = hash_hmac('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE), $this->webhook_key);
        
        $this->output_info('Generated payload for shipping method webhook');
        
        if ($this->simulate_only) {
            $this->output_warning('Simulation mode: Not actually processing webhook');
        } else {
            $this->output_info('Processing webhook...');
            $_SERVER['HTTP_X_FLIZ_SIGNATURE'] = $signature;
            $result = $this->gateway->set_shipping_method($payload);
            $this->output_success('Webhook processed');
            $this->output_info('New total cost: ' . $result['totalCost']);
        }
    }
    
    /**
     * Test cashback update webhook
     */
    private function test_cashback_update() {
        $this->output_test_header('Cashback Update Webhook Test');
        
        $this->output_info('Testing cashback information update webhook...');
        
        $payload = array(
            'updateCashbackInfo' => true,
            'firstPurchaseAmount' => 10.0,
            'amount' => 5.0 // standard amount
        );
        
        // Add signature
        $signature = hash_hmac('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE), $this->webhook_key);
        
        $this->output_info('Generated payload for cashback update webhook');
        $this->output_info('First Purchase Amount: ' . $payload['firstPurchaseAmount'] . '%');
        $this->output_info('Standard Amount: ' . $payload['amount'] . '%');
        
        if ($this->simulate_only) {
            $this->output_warning('Simulation mode: Not actually processing webhook');
        } else {
            $this->output_info('Processing webhook...');
            $_SERVER['HTTP_X_FLIZ_SIGNATURE'] = $signature;
            $class = new ReflectionClass('Flizpay_Webhook_Helper');
            $method = $class->getMethod('update_cashback_info');
            $method->setAccessible(true);
            $method->invoke($this->webhook_helper, $payload);
            $this->output_success('Webhook processed');
            
            // Verify cashback data
            $settings = get_option('woocommerce_flizpay_settings');
            $cashback = isset($settings['flizpay_cashback']) ? $settings['flizpay_cashback'] : array();
            $this->output_info('Updated cashback data in settings:');
            $this->output_info('First Purchase: ' . (isset($cashback['first_purchase_amount']) ? $cashback['first_purchase_amount'] : 'Not set'));
            $this->output_info('Standard Amount: ' . (isset($cashback['standard_amount']) ? $cashback['standard_amount'] : 'Not set'));
        }
    }
    
    /**
     * Test connection webhook
     */
    private function test_connection() {
        $this->output_test_header('Connection Test Webhook');
        
        $this->output_info('Testing connection test webhook...');
        
        $payload = array(
            'test' => true
        );
        
        // Add signature
        $signature = hash_hmac('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE), $this->webhook_key);
        
        $this->output_info('Generated payload for connection test webhook');
        
        if ($this->simulate_only) {
            $this->output_warning('Simulation mode: Not actually processing webhook');
        } else {
            $this->output_info('Processing webhook...');
            $_SERVER['HTTP_X_FLIZ_SIGNATURE'] = $signature;
            $class = new ReflectionClass('Flizpay_Webhook_Helper');
            $method = $class->getMethod('update_webhook_status');
            $method->setAccessible(true);
            $method->invoke($this->webhook_helper, true);
            $this->output_success('Webhook processed');
            
            // Verify webhook status
            $settings = get_option('woocommerce_flizpay_settings');
            $webhook_alive = isset($settings['flizpay_webhook_alive']) ? $settings['flizpay_webhook_alive'] : 'no';
            $this->output_info('Webhook alive status: ' . $webhook_alive);
        }
    }
    
    /**
     * Clean up test order
     */
    private function cleanup_test_order() {
        $this->output_test_header('Test Cleanup');
        
        if ($this->test_order_id) {
            $this->output_info('Cleaning up test order...');
            $order = wc_get_order($this->test_order_id);
            if ($order) {
                $order->delete(true);
                $this->output_success('Test order deleted');
            }
        }
    }
    
    /**
     * Output formatted header
     */
    private function output_header($text) {
        echo "\n";
        echo "===================================================================\n";
        echo " $text \n";
        echo "===================================================================\n";
    }
    
    /**
     * Output test section header
     */
    private function output_test_header($text) {
        echo "\n";
        echo "-------------------------------------------------------------------\n";
        echo " $text \n";
        echo "-------------------------------------------------------------------\n";
    }
    
    /**
     * Output success message
     */
    private function output_success($text) {
        echo "\033[32m✓ $text\033[0m\n";
    }
    
    /**
     * Output error message
     */
    private function output_error($text) {
        echo "\033[31m✗ $text\033[0m\n";
    }
    
    /**
     * Output warning message
     */
    private function output_warning($text) {
        echo "\033[33m! $text\033[0m\n";
    }
    
    /**
     * Output info message
     */
    private function output_info($text) {
        echo "\033[36m• $text\033[0m\n";
    }
}

// Instantiate and run the tests
$webhook_tests = new Flizpay_Webhook_Test();
$webhook_tests->run_tests();