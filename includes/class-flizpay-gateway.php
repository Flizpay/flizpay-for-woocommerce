<?php
/**
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'flizpay_init_gateway_class');
function flizpay_init_gateway_class()
{

    class WC_Flizpay_Gateway extends WC_Payment_Gateway
    {
        static $VERSION = "1.0.0";

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {
            $this->i18n = new Flizpay_i18n();
            $this->id = 'flizpay';
            $this->has_fields = true;
            $this->method_title = 'FLIZpay Plugin';
            $this->method_description = 'FLIZpay Plugin WooCommerce';
            $this->icon = 'https://woocommerce-plugin-assets.s3.eu-central-1.amazonaws.com/fliz-checkout-logo.png';

            // Method with all the options fields
            $this->init_form_fields();
            // Load the setting.
            $this->init_settings();
            // Ensure text domain is loaded
            $this->i18n->load_plugin_textdomain();

            $this->enabled = $this->get_option('flizpay_enabled');
            $this->api_key = $this->get_option('flizpay_api_key');
            $this->webhook_key = $this->get_option('flizpay_webhook_key');
            $this->webhook_url = $this->get_option('flizpay_webhook_url');
            $this->flizpay_webhook_alive = $this->get_option('flizpay_webhook_alive');
            $this->cashback = $this->get_cashback_data($this->api_key);

            $this->init_gateway_info();

            add_action('wp_ajax_test_gateway_connection', array($this, 'test_gateway_connection'));

            // Webhook handler
            add_action('init', array($this, 'register_webhook_endpoint'));
            add_action('template_redirect', array($this, 'handle_webhook_request'));
        }

        public function init_gateway_info()
        {
            if (
                isset($this->webhook_key) &&
                isset($this->webhook_url) &&
                $this->flizpay_webhook_alive === 'yes' &&
                !is_null($this->cashback)
            ) {
                $title = sprintf(__('cashback-title', 'flizpay-for-woocommerce'), $this->cashback);
                $description = sprintf(__('cashback-description', 'flizpay-for-woocommerce'), $this->cashback);
                $this->update_option('flizpay_cashback', $this->cashback);
            } else {
                $title = __('title', 'flizpay-for-woocommerce');
                $description = __('description', 'flizpay-for-woocommerce');
            }

            // Ensure these are not set to the fallback strings before updating
            if ($title !== 'cashback-title' && $description !== 'cashback-description') {
                $this->title = $title;
                $this->description = $description;
                $this->update_option('title', $this->title);
                $this->update_option('description', $this->description);
            }
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

        public function get_cashback_data(string $api_key)
        {
            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('fetch_cashback_data', null, false);

            $active_cashback = null;

            if (isset($response['cashbacks'])) {
                foreach ($response['cashbacks'] as $cashback) {
                    if ($cashback['active']) {
                        $active_cashback = $cashback;
                    }
                }
            }

            return $active_cashback['amount'] ?? null;
        }

        public function webhook_authenticate($data)
        {
            $key = $this->get_option('flizpay_webhook_key');

            $signature = sanitize_text_field($_SERVER['HTTP_X_FLIZ_SIGNATURE']);

            $signedData = hash_hmac('sha256', wp_json_encode($data), $key);

            return hash_equals($signature, $signedData);
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
                    wp_send_json_error('Invalid Request', 422);
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
                $discount = (float) $data['originalAmount'] - (float) $data['amount'];
                if ($discount > 0) {
                    $order->set_discount_total((float) $order->get_discount_total()
                        + $discount);
                    $order->set_total($data['amount']);
                    $order->add_order_note('FLIZ Cashback Applied: ' . $data['currency'] . sanitize_text_field($discount));
                }

                WC()->cart->empty_cart();

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

        /**
         * Check if we are on the order-pay (Customer Payment Page) page.
         */
        private function is_order_pay_page()
        {
            global $wp;

            return isset($wp->query_vars['order-pay']);
        }

        public function is_available()
        {
            if ($this->is_order_pay_page()) {
                return false; // Do not display in admin order management
            }

            $available = $this->get_option('flizpay_enabled') === 'yes' &&
                $this->get_option('flizpay_webhook_alive') === 'yes';

            return $available;
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {
            $this->form_fields = apply_filters('flizpay_load_settings', true);
        }

        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            $redirectUrl = $this->create_transaction($order);

            if ($redirectUrl) {
                return array('result' => 'success', 'redirect' => $redirectUrl);
            } else {
                wc_add_notice('Error creating FLIZpay transaction. Please try again later.');
                return array(
                    'result' => 'failure',
                    'redirect' => ''
                );
            }

        }

        public function create_transaction($order)
        {
            $flizpay_setting = get_option('woocommerce_flizpay_settings');
            $api_key = $flizpay_setting['flizpay_api_key'];
            $body = array(
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'externalId' => $order->get_id(),
                'successUrl' => $order->get_checkout_order_received_url(),
                'failureUrl' => get_home_url() . '/flizpay-payment-fail',
            );

            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('create_transaction', $body);

            return $response['redirectUrl'];
        }
    }
}