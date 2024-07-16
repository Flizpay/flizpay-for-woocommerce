<?php
/**
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'flizpay_init_gateway_class');
function flizpay_init_gateway_class()
{

    class WC_Flizpay_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {
            $this->id = 'flizpay';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'Flizpay Gateway';
            $this->method_description = 'Flizpay payment gateway for WooCommerce';

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->api_key = $this->get_option('flizpay_api_key');


            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_ajax_test_gateway_connection', array($this, 'test_gateway_connection'));

            // Webhook handler
            add_action('init', array($this, 'register_webhook_endpoint'));
            add_action('template_redirect', array($this, 'handle_webhook_request'));

            // Payment form handler
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // One page checkout, express checkout fields
            // wp-block-woocommerce-checkout-express-payment-block - Class of the express checkout block
            // add_action('woocommerce_one_page_checkout_order_review', array($this, 'payment_fields'), 10, 1);
            // add_action('woocommerce_before_checkout_form', array($this, 'payment_fields'), 10, 1);
        }

        public function test_gateway_connection()
        {
            check_ajax_referer('test_connection_nonce', 'nonce');

            $api_key = sanitize_text_field($_POST['api_key']);

            $this->update_option('enabled', 'no');
            $this->update_option('flizpay_webhook_alive', 'no');
            $this->update_option('flizpay_webhook_key', $this->get_webhook_key($api_key));
            $this->update_option('flizpay_webhook_url', $this->generate_webhook_url($api_key));
            $this->update_option('flizpay_api_key', $api_key);

            wp_send_json_success(array('webhookUrl' => $this->get_option('flizpay_webhook_url')));

        }
        public function get_webhook_key(string $api_key): string
        {
            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('generate_webhook_key');

            $webhookKey = $response['webhookKey'];

            return $webhookKey;
        }

        public function generate_webhook_url(string $api_key): string
        {
            $webhookUrl = home_url('/flizpay-webhook?flizpay-webhook=1&source=woocommerce');

            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('save_webhook_url', array('webhookUrl' => $webhookUrl));

            $webhookUrlResponse = $response['webhookUrl'];

            if (strcmp($webhookUrlResponse, $webhookUrl) !== 0) {
                return wp_send_json_error('Incorrect WebhookURL: ' . $webhookUrlResponse);
            }

            return $webhookUrlResponse;
        }

        public function webhook_authenticate($data)
        {
            $key = $this->get_option('flizpay_webhook_key');

            $signature = $_SERVER['HTTP_X_FLIZ_SIGNATURE'];

            $signedData = hash_hmac('sha256', json_encode($data), $key);

            return hash_equals($signedData, $signature);
        }

        public function register_webhook_endpoint()
        {
            add_rewrite_tag('%flizpay-webhook%', '([^&]+)');
            add_rewrite_rule('^flizpay-webhook/?', 'index.php?flizpay-webhook=1&source=woocommerce', 'top');

            if (empty($this->get_option('flizpay_webhook_key'))) {
                flush_rewrite_rules();
            }

        }

        public function handle_webhook_request()
        {
            global $wp;

            if (isset($wp->query_vars['flizpay-webhook'])) {
                $data = json_decode(file_get_contents('php://input'), true);

                if (json_last_error() === JSON_ERROR_NONE && $this->webhook_authenticate($data)) {
                    if (isset($data['test'])) {
                        $this->update_option('flizpay_webhook_alive', 'yes');
                        $this->update_option('flizpay_enabled', 'yes');
                        wp_send_json_success(array('alive' => true), 200);
                    } else {
                        // Process the webhook data
                        $this->process_webhook_data($data);
                        // Respond with success
                        wp_send_json_success('Order updated successfully', 200);
                    }
                } else {
                    wp_send_json_error('Invalid Request', 400);
                }
            }

            return; // Do not process the request
        }

        public function process_webhook_data($data)
        {
            // Ensure the necessary data is available
            if (!isset($data['metadata']['orderId']) || !isset($data['status'])) {
                wp_send_json_error('Missing order_id or status', 400);
            }

            // Get the WooCommerce order ID
            $order_id = intval($data['metadata']['orderId']);
            $status = sanitize_text_field($data['status']);

            // Load the WooCommerce order
            $order = wc_get_order($order_id);

            if (!$order) {
                wp_send_json_error('Order not found', 404);
            }

            if ($status === 'completed') {
                $order->payment_complete($data['transactionId']);

                if (isset($data['transactionId'])) {
                    $order->add_order_note('FLIZ transaction ID: ' . sanitize_text_field($data['transactionId']));
                }
            } else if ($status === 'failed') {
                $order->cancel_order();
                $order->update_status('cancelled', 'Updated via FLIZpay plugin', true);
            } else {
                return;
            }
            // Save the order
            $order->save();
        }

        public function is_available()
        {
            $available = $this->get_option('flizpay_enabled');

            return $available;
        }

        public function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wp_kses_post($this->description));
            }
            echo '<img src="' . esc_url($this->icon) . '" alt="Flizpay Logo" style="max-width: 100px;">';
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            // Mark as on-hold (we're awaiting the payment)
            $order->update_status('on-hold', __('Awaiting Flizpay payment', 'flizpay'));

            // Reduce stock levels
            wc_reduce_stock_levels($order_id);

            // Return thank you page redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {
            $this->form_fields = apply_filters('flizpay_load_settings', true);
        }
    }
}