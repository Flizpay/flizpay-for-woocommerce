<?php
/**
 * The Payment Gateway class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'flizpay_init_gateway_class');
function flizpay_init_gateway_class()
{

    class WC_Flizpay_Gateway extends WC_Payment_Gateway
    {
        static $VERSION = "1.2.1";

        public $icon;
        public $title;
        public $description;
        public $cashback;
        public $i18n;
        public $api_key;
        public $webhook_key;
        public $webhook_url;
        public $flizpay_webhook_alive;
        public $flizpay_display_logo;
        public $flizpay_display_description;
        public $flizpay_display_headline;

        /**
         * The class constructor will set FLIZ id, load translations, description, icon and etc. 
         * It's also responsible for instantiating all FLIZ variables like API KEY and WEBHOOK KEY
         * Additionally it will obtain the Cashback information of the merchant from the transients
         * or from the API and also apply the translations.
         * The constructor will also register the template redirects for webhooks .
         * 
         * @since 1.0.0
         */
        public function __construct()
        {
            $this->i18n = new Flizpay_i18n();
            $this->id = 'flizpay';
            $this->has_fields = true;
            $this->method_title = 'FLIZpay Plugin';
            $this->method_description = 'FLIZpay Plugin WooCommerce';

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
            $this->flizpay_display_logo = $this->get_option('flizpay_display_logo');
            $this->flizpay_display_description = $this->get_option('flizpay_display_description');
            $this->flizpay_display_headline = $this->get_option('flizpay_display_headline');

            if ($this->flizpay_display_logo === 'yes') {
                $this->icon = plugins_url() . '/' . basename(dirname(__DIR__)) . '/assets/images/fliz-checkout-logo-with-banks.svg';
            }

            $this->cashback = $this->get_cashback_data();

            $this->init_gateway_info();

            add_action('wp_ajax_test_gateway_connection', array($this, 'test_gateway_connection'));

            // Webhook handler
            add_action('init', array($this, 'register_webhook_endpoint'));
            add_action('template_redirect', array($this, 'handle_webhook_request'));
        }

        /**
         * Apply translations to FLIZ gateway title and description
         * The title and description will vary depending on whether the cashback is active or not.
         * 
         * @return void
         * 
         * @since 1.2.0
         */
        public function init_gateway_info()
        {
            $this->set_cashback_info();
            $this->set_title();
            $this->set_description();
        }

        /**
         * Sets the cashback title and description if applicable.
         * @return void
         * 
         * @since 1.2.0
         */
        private function set_cashback_info()
        {
            if ($this->is_cashback_available()) {
                $title = sprintf(__('cashback-title', 'flizpay-for-woocommerce'), $this->cashback);
                $description = sprintf(__('cashback-description', 'flizpay-for-woocommerce'), $this->cashback);
                $this->update_option('flizpay_cashback', $this->cashback);
            } else {
                $title = __('title', 'flizpay-for-woocommerce');
                $description = __('description', 'flizpay-for-woocommerce');
            }
            $this->title = $this->flizpay_display_headline === 'yes' ? $title : 'FLIZpay';
            $this->description = $this->flizpay_display_description === 'yes' ? $description : null;
        }

        /**
         * Check if cashback-related information is available.
         * @return bool
         * 
         * @since 1.2.0
         */
        private function is_cashback_available()
        {
            return isset($this->webhook_key) &&
                isset($this->webhook_url) &&
                $this->flizpay_webhook_alive === 'yes' &&
                !is_null($this->cashback);
        }

        /**
         * Sets the title for the payment gateway.
         * @return void
         * 
         * @since 1.2.0
         */
        private function set_title()
        {
            if ($this->is_default_translation($this->title)) {
                if ($this->flizpay_display_headline === 'yes') {
                    $this->title = !is_null($this->cashback)
                        ? 'FLIZpay - ' . $this->cashback . '% Cashback'
                        : 'FLIZpay - Deine Wahl zählt!';
                } else {
                    $this->title = 'FLIZpay';
                }
            }
            $this->update_option('title', $this->title);
        }

        /**
         * Sets the description for the payment gateway.
         * @return void
         * 
         * @since 1.2.0
         */
        private function set_description()
        {
            if ($this->is_default_translation($this->description)) {
                if ($this->flizpay_display_description === 'yes') {
                    $this->description = 'Zahlungsmethoden belasten kleine Unternehmen mit hohen Gebühren. FLIZpay ist für alle kostenlos, deine Wahl ist also wichtig. Melde dich in 60 Sekunden an.';
                }
            }
            $this->update_option('description', $this->description);
        }

        /**
         * Checks if the translation is using the fallback/default string.
         *
         * @param string $value The current translation value.
         * @param string $key The translation key.
         * @return bool
         * @since 1.2.0
         */
        private function is_default_translation($value)
        {
            $fallbacks = [
                'cashback-title',
                'cashback-description',
                'title',
                'description'
            ];

            return in_array($value, $fallbacks);
        }

        /**
         * Function called after the admin settings are saved. 
         * It's responsible for testing and assuring the 2-way connection
         * between the merchant's site and FLIZpay servers
         * 
         * It's also responsible for defining the merchant webhook URL and
         * obtaining the webhook secret
         * 
         * @return void
         * 
         * @since 1.0.0
         */
        public function test_gateway_connection()
        {
            check_ajax_referer('test_connection_nonce', 'nonce');
            if (isset($_POST['api_key'])) {
                $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));

                $this->update_option('enabled', 'no');
                $this->update_option('flizpay_webhook_alive', 'no');
                $this->update_option('flizpay_webhook_key', $this->get_webhook_key($api_key));
                $this->update_option('flizpay_webhook_url', $this->generate_webhook_url($api_key));
                $this->update_option('flizpay_api_key', $api_key);

                if (isset($_POST['display_logo'])) {
                    $display_logo = sanitize_text_field(wp_unslash($_POST['display_logo']));
                    $this->update_option('flizpay_display_logo', $display_logo);
                }

                if (isset($_POST['display_description'])) {
                    $display_description = sanitize_text_field(wp_unslash($_POST['display_description']));
                    $this->update_option('flizpay_display_description', $display_description);
                }

                if (isset($_POST['display_headline'])) {
                    $display_headline = sanitize_text_field(wp_unslash($_POST['display_headline']));
                    $this->update_option('flizpay_display_headline', $display_headline);
                }

                wp_send_json_success(array('webhookUrl' => $this->get_option('flizpay_webhook_url')));
            }

        }

        /**
         * Uses the FLIZ API class to obtain the webhook key 
         * for further webhook authentication between the merchant's site 
         * and FLIZ servers.
         * 
         * @param string $api_key
         * @return string
         * 
         * @since 1.0.0
         */
        public function get_webhook_key(string $api_key): string
        {
            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('generate_webhook_key');

            $webhookKey = $response['webhookKey'];

            return $webhookKey;
        }

        /**
         * Uses the FLIZ API class to register a Webhook URL to the merchant
         * @param string $api_key
         * @return string
         * 
         * @since 1.0.0
         */
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

        /**
         * Obtain the current cashback value of the merchant from the transient
         * or by making an API call using the FLIZ API class when the transient 
         * is expired.
         * 
         * @return string | null
         * 
         * @since 1.0.0
         */
        public function get_cashback_data()
        {
            $cashback_data = get_transient('flizpay_cashback_transient');

            if ($cashback_data === false && !empty($this->api_key)) {

                $client = WC_Flizpay_API::get_instance($this->api_key);

                $response = $client->dispatch('fetch_cashback_data', null, false);

                if (isset($response['cashbacks'])) {
                    foreach ($response['cashbacks'] as $cashback) {
                        if ($cashback['active']) {
                            set_transient('flizpay_cashback_transient', $cashback['amount'], 300);
                            return $cashback['amount'];
                        }
                    }
                }
            }

            return !empty($cashback_data) ? $cashback_data : null;
        }

        /**
         * Standard authentication of the payload received via webhook. 
         * The authentication method uses a HMAC hash with sha256 algorithm.
         * The webhook key is used as the key to encode and decode the message. 
         * More info in our docs at https://docs.flizpay.de
         * 
         * @param array $data
         * @return bool | void
         * 
         * @since 1.0.0
         */
        public function webhook_authenticate($data)
        {
            $key = $this->get_option('flizpay_webhook_key');

            if (isset($_SERVER['HTTP_X_FLIZ_SIGNATURE'])) {
                $signature = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FLIZ_SIGNATURE']));

                $signedData = hash_hmac('sha256', wp_json_encode($data), $key);

                return hash_equals($signature, $signedData);
            }
        }

        /**
         * Register the webhook endpoint with the merchant's wordpress site
         * When the webhook key is not set, this class will also perform a flush in 
         * the current rewrite rules to make sure that the webhook url is properly registered.
         * 
         * @return void
         * 
         * @since 1.0.0
         */
        public function register_webhook_endpoint()
        {
            add_rewrite_tag('%flizpay-webhook%', '([^&]+)');
            add_rewrite_rule('^flizpay-webhook/?', 'index.php?flizpay-webhook=1&source=woocommerce', 'top');

            if (empty($this->get_option('flizpay_webhook_key'))) {
                flush_rewrite_rules();
            }

        }

        /**
         * Entrypoint for all incoming webhook requests. 
         * This method will attempt to authenticate the payload and update the order
         * accordingly, given the status informed in the payload. 
         * It's also handling the 2-way test connection of the integration.
         * 
         * @return void
         * 
         * @since 1.0.0
         */
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

        /**
         * Function responsible for the actual processing of the webhook payload
         * Updates the order and its metadata, and applies the merchant cashback value as discount.
         * It empties the cart on success and cancels the order on failure.
         * 
         * @param array $data
         * @return void
         * 
         * @since 1.0.0
         */
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
                // Set payment status to completed 
                $order->payment_complete($data['transactionId']);

                //Get the FLIZ Cashback discount
                $fliz_discount = (float) $data['originalAmount'] - (float) $data['amount'];

                if ($fliz_discount > 0) {
                    $line_items = $order->get_items();
                    $shipping_items = $order->get_items('shipping');

                    // Apply additional discount to line items
                    foreach ($line_items as $item) {
                        $item_subtotal = $item->get_total();

                        // Calculate the additional discount based on the already discounted total
                        $discount_amount_fliz = ($item_subtotal * $this->cashback) / 100;

                        // Set the new total for the line item after applying the additional discount
                        $new_total = $item_subtotal - $discount_amount_fliz;
                        $item->set_total($new_total);

                        // Recalculate taxes for the item if applicable
                        $item->calculate_taxes();

                        // Save the item changes
                        $item->save();
                    }

                    // Apply additional discount to shipping items
                    foreach ($shipping_items as $shipping) {
                        $shipping_total = $shipping->get_total();

                        // Calculate the additional discount for shipping
                        $discount_amount_fliz = ($shipping_total * $this->cashback) / 100;

                        // Set the new shipping total after applying the discount
                        $new_shipping_total = $shipping_total - $discount_amount_fliz;
                        $shipping->set_total($new_shipping_total);

                        // Recalculate taxes for shipping if applicable
                        $shipping->calculate_taxes();

                        // Save the shipping item changes
                        $shipping->save();
                    }

                    // Recalculate the overall order totals
                    $order->calculate_totals();
                    $order->save();
                    $order->add_order_note('FLIZ Cashback Applied: ' . $data['currency'] . sanitize_text_field($fliz_discount));
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
         * 
         * @return bool
         * 
         * @since 1.0.0
         */
        private function is_order_pay_page()
        {
            global $wp;

            return isset($wp->query_vars['order-pay']);
        }

        /**
         * This plugin is only available outside of the admin order pay page.
         * It will also be marked as unavailable when the 2-way webhook connection was not established
         * and when the configuration haven't been completed at all.
         * 
         * @return bool
         * 
         * @since 1.0.0
         */
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
         * Plugin options, load the current settings
         * 
         * @return void
         * 
         * @since 1.0.0
         */
        public function init_form_fields()
        {
            $this->form_fields = apply_filters('flizpay_load_settings', true);
        }

        /**
         * Function called in the moment of checkout when choosing to pay with FLIZ
         * It's responsible for creating the transaction using the FLIZ API Class
         * 
         * @param string $order_id
         * @return array
         * 
         * @since 1.0.0
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            $redirectUrl = $this->create_transaction($order);

            if ($redirectUrl) {
                return array('result' => 'success', 'redirect' => $redirectUrl, 'order_id' => $order_id);
            } else {
                wc_add_notice('Error creating FLIZpay transaction. Please try again later.');
                return array(
                    'result' => 'failure',
                    'redirect' => ''
                );
            }

        }

        /**
         * Calls FLIZ API Class to create a transaction with the order data. 
         * It will return the redirect URL to the FLIZ checkout page
         * 
         * @param array $order
         * @return string
         * 
         * @since 1.0.0
         */
        public function create_transaction($order)
        {
            $flizpay_setting = get_option('woocommerce_flizpay_settings');
            $api_key = $flizpay_setting['flizpay_api_key'];
            $body = array(
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'externalId' => $order->get_id(),
                'successUrl' => $order->get_checkout_order_received_url(),
                'failureUrl' => get_home_url() . '/payment-failed',
            );

            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('create_transaction', $body, false);

            return $response['redirectUrl'];
        }
    }
}