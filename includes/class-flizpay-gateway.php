<?php
/**
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_filter('https_local_ssl_verify', '__return_false');
add_filter('https_ssl_verify', '__return_false');
add_filter('block_local_requests', '__return_false');
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
            $this->icon = apply_filters('woocommerce_flizpay_icon', plugin_dir_url(__FILE__) . '../public/images/logo.png');
            $this->has_fields = true;
            $this->method_title = 'Flizpay Gateway';
            $this->method_description = 'Flizpay payment gateway for WooCommerce';

            // Method with all the options fields
            $this->init_form_fields();

            // Load the setting.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->api_key = $this->get_option('flizpay_api_key');
            $this->webhook_key = $this->get_option('flizpay_webhook_key');
            $this->webhook_url = $this->get_option('flizpay_webhook_url');


            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('wp_ajax_test_gateway_connection', array($this, 'test_gateway_connection'));

            // Webhook handler
            add_action('init', array($this, 'register_webhook_endpoint'));
            add_action('template_redirect', array($this, 'handle_webhook_request'));

            // Payment form handler
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        }

        public function test_gateway_connection()
        {
            check_ajax_referer('test_connection_nonce', 'nonce');

            $api_key = sanitize_text_field($_POST['api_key']);

            $webhookKey = $this->get_webhook_key($api_key);

            $webhookUrl = $this->generate_webhook_url($api_key);

            $this->update_option('flizpay_webhook_url', $webhookUrl);
            $this->update_option('flizpay_webhook_key', $webhookKey);
            $this->update_option('flizpay_api_key', $api_key);

            wp_send_json_success(array('webhookUrl' => $webhookUrl));

        }
        public function get_webhook_key(string $api_key): string
        {
            $result = wp_remote_get(
                'http://localhost:8081/business/generate-webhook-key',
                array('headers' => array('x-api-key' => $api_key))
            );

            $result = wp_remote_retrieve_body($result);

            if (is_wp_error($result)) {
                return wp_send_json_error('Connection failed. ' . wp_remote_retrieve_response_message($result));
            }

            $body = json_decode($result, true);

            if (empty($body)) {
                return wp_send_json_error('Empty WebhookKey body.' . $body);
            }

            if (!json_last_error() === JSON_ERROR_NONE) {
                return wp_send_json_error('JSON ERROR. ' . json_last_error());
            }

            if (empty($body['data']) || empty($body['data']['webhookKey'])) {
                return wp_send_json_error('Empty webhook key. ' . $body['message']);
            }

            $webhookKey = $body['data']['webhookKey'];

            return $webhookKey;
        }

        public function generate_webhook_url(string $api_key): string
        {
            $webhookUrl = home_url('/flizpay-webhook/index.php?flizpay-webhook=1&source=woocommerce');

            $result = wp_remote_post(
                'http://localhost:8081/business/edit',
                array(
                    'headers' => array(
                        'x-api-key' => $api_key,
                        'Content-Type' => 'application/json'
                    ),
                    'body' => wp_json_encode(array('webhookUrl' => $webhookUrl)),
                    'data_format' => 'body',
                )
            );


            if (is_wp_error($result)) {
                return wp_send_json_error('Connection failed. ' . wp_remote_retrieve_result_message($result));
            }

            $body = $result['body'];

            if (empty($body)) {
                return wp_send_json_error('Empty WebhookURL result.' . $result);
            }

            if (
                empty(json_decode($body, true)['data']['webhookUrl']) ||
                strcmp(json_decode($body, true)['data']['webhookUrl'], $webhookUrl) !== 0
            ) {
                return wp_send_json_error('Incorrect WebhookURL: ' . $body);
            }

            return $webhookUrl;
        }

        public function register_webhook_endpoint()
        {
            add_rewrite_rule('^flizpay-webhook/?', 'index.php?flizpay-webhook=1&source=woocommerce', 'top');
            add_rewrite_tag('%flizpay-webhook%', '([^&]+)');
        }

        public function handle_webhook_request()
        {
            global $wp;

            if (
                isset($wp->query_vars['flizpay-webhook']) &&
                isset($wp->query_vars['source'])
            ) {
                $body = file_get_contents('php://input');
                $data = json_decode($body, true);

                if (json_last_error() === JSON_ERROR_NONE || $data['key'] !== $this->get_option('flizpay_webhook_key')) {
                    // Process the webhook data
                    $this->process_webhook_data($data);
                } else {
                    wp_send_json_error('Invalid JSON', 400);
                }
            }
        }

        public function process_webhook_data($data)
        {
            // Ensure the necessary data is available
            if (!isset($data['order_id']) || !isset($data['status'])) {
                wp_send_json_error('Missing order_id or status', 400);
            }

            // Get the WooCommerce order ID
            $order_id = intval($data['order_id']);
            $status = sanitize_text_field($data['status']);

            // Load the WooCommerce order
            $order = wc_get_order($order_id);

            if (!$order) {
                wp_send_json_error('Order not found', 404);
            }

            // Update the order status
            $order->update_status($status, 'Order updated via Flizpay webhook', true);

            // Optionally add additional data to the order
            if (isset($data['additional_info'])) {
                $order->add_order_note('Additional Info: ' . sanitize_text_field($data['additional_info']));
            }

            // Save the order
            $order->save();

            // Respond with success
            wp_send_json_success('Order updated successfully', 200);
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

        public function receipt_page($order)
        {
            echo '<p>' . __('Thank you for your order, please click the button below to pay with Flizpay.', 'flizpay') . '</p>';
            echo '<a class="button alt" href="https://checkout.flizpay.com/?order_id=' . esc_attr($order->get_id()) . '" target="_blank">' . __('Pay with Flizpay', 'flizpay') . '</a>';
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {
            $this->form_fields = apply_filters('flizpay_load_settings', true);
        }

        /**
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts()
        {

        }

        /**
         * Fields validation, more in Step 5
         */
        public function validate_fields()
        {

        }
    }
}