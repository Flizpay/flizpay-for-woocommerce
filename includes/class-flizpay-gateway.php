<?php
/**
 * The Payment Gateway class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'flizpay_init_gateway_class');
function flizpay_init_gateway_class()
{

    class WC_Flizpay_Gateway extends WC_Payment_Gateway
    {
        static $VERSION = "2.0.5";

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
        public $flizpay_enable_express_checkout;
        public $flizpay_express_checkout_pages;
        public $flizpay_express_checkout_theme;
        public $flizpay_express_checkout_title;

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

            $this->enabled = $this->get_option('flizpay_enabled') === 'yes';
            $this->api_key = $this->get_option('flizpay_api_key');
            $this->webhook_key = $this->get_option('flizpay_webhook_key');
            $this->webhook_url = $this->get_option('flizpay_webhook_url');
            $this->flizpay_webhook_alive = $this->get_option('flizpay_webhook_alive');
            $this->flizpay_display_logo = $this->get_option('flizpay_display_logo');
            $this->flizpay_display_description = $this->get_option('flizpay_display_description');
            $this->flizpay_display_headline = $this->get_option('flizpay_display_headline');
            $this->flizpay_enable_express_checkout = $this->get_option('flizpay_enable_express_checkout');
            $this->flizpay_express_checkout_pages = $this->get_option('flizpay_express_checkout_pages');
            $this->flizpay_express_checkout_theme = $this->get_option('flizpay_express_checkout_theme');

            if ($this->flizpay_display_logo === 'yes') {
                $this->icon = plugins_url() . '/' . basename(dirname(__DIR__)) . '/assets/images/fliz-checkout-logo.svg';
            }

            $this->cashback = $this->get_cashback_data();

            $this->init_gateway_info();

            //Admin options setup handler
            add_action('woocommerce_update_options_checkout_flizpay', array($this, 'test_gateway_connection'));

            // Webhook handler
            add_action('init', array($this, 'register_webhook_endpoint'));
            add_action('template_redirect', array($this, 'handle_webhook_request'));

            // Order placed e-mail handler
            add_filter('woocommerce_email_enabled_new_order', array($this, 'disable_new_order_email_for_flizpay'), 10, 2);

            // Express checkout handler
            add_action("wp_ajax_flizpay_express_checkout", array($this, "flizpay_express_checkout"));
            add_action("wp_ajax_nopriv_flizpay_express_checkout", array($this, "flizpay_express_checkout"));
        }

        /**
         * Disable sending notifications to the admin when an order is placed with FLIZ
         * Since the order wasn't yet paid on this moment, we don't notify
         * All e-mails for order paid and so on shall be sent normally
         * 
         * @return mixed | bool
         * 
         * @since 1.4.3
         */
        public function disable_new_order_email_for_flizpay($enabled, $order)
        {
            if (
                $order &&
                ($order->get_status() === 'checkout-draft' || $order->get_status() === 'pending') &&
                'flizpay' === $order->get_payment_method()
            ) {
                // If FLIZ order is not yed paid, disable the New Order email
                return false;
            }
            return $enabled;
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
                $shop_name = get_bloginfo('name');
                $title = sprintf(
                    __('cashback-title', 'flizpay-for-woocommerce'),
                    floatval($this->cashback['first_purchase_amount']) > 0
                    ? $this->cashback['first_purchase_amount']
                    : $this->cashback['standard_amount']
                );
                $express_checkout_title = sprintf(
                    __('cashback-express-title', 'flizpay-for-woocommerce'),
                    floatval($this->cashback['first_purchase_amount']) > 0
                    ? $this->cashback['first_purchase_amount']
                    : $this->cashback['standard_amount']
                );
                switch ($this->get_cashback_type()) {
                    case 'both':
                        $description = sprintf(
                            __('cashback-description-both', 'flizpay-for-woocommerce'),
                            $shop_name,
                            $this->cashback['standard_amount']
                        );
                        break;
                    case 'first':
                        $description = sprintf(
                            __('cashback-description-first', 'flizpay-for-woocommerce'),
                            $shop_name
                        );
                        break;
                    case 'standard':
                        $description = sprintf(
                            __('cashback-description-standard', 'flizpay-for-woocommerce'),
                            $this->cashback['standard_amount'],
                            $shop_name
                        );
                        break;
                }
                $this->update_option('flizpay_cashback', $this->cashback);
            } else {
                $title = __('title', 'flizpay-for-woocommerce');
                $description = __('description', 'flizpay-for-woocommerce');
                $express_checkout_title = __('express-title', 'flizpay-for-woocommerce');
            }
            $this->title = $this->flizpay_display_headline === 'yes' ? $title : 'FLIZpay';
            $this->description = $this->flizpay_display_description === 'yes' ? $description : null;
            $this->flizpay_express_checkout_title = $express_checkout_title;
        }

        /**
         * Define the type of cashback enabled for the business
         * @return string { both, standard, first }
         * @since 1.4.3
         */
        private function get_cashback_type()
        {
            $first = floatval($this->cashback['first_purchase_amount']);
            $amount = floatval($this->cashback['standard_amount']);

            if ($first > 0 && $amount > 0)
                return 'both';
            else if ($first > 0)
                return 'first';
            else
                return 'standard';
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
                !is_null($this->cashback) &&
                (!is_null($this->cashback['first_purchase_amount']) ||
                    !is_null($this->cashback['standard_amount']));
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
                        ? 'FLIZpay - ' . floatval($this->cashback['first_purchase_amount']) > 0 ? $this->cashback['first_purchase_amount'] : $this->cashback['standard_amount'] . '% Sofort-Cashback'
                        : 'FLIZpay - Die Zahlungsrevolution';
                } else {
                    $this->title = 'FLIZpay';
                }
                $this->flizpay_express_checkout_title = !is_null($this->cashback)
                    ? 'FLIZpay - ' . floatval($this->cashback['first_purchase_amount']) > 0 ? $this->cashback['first_purchase_amount'] : $this->cashback['standard_amount'] . '% Sofort-Cashback'
                    : 'Jetzt zahlung mit FLIZpay';
            }
            $this->update_option('title', $this->title);
            $this->update_option('flizpay_express_checkout_title', $this->flizpay_express_checkout_title);
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
                    $this->description = 'Sichere Zahlungen in direkter Zusammenarbeit mit deiner Bank, unterstützung kleiner Unternehmen, und deine Daten bleiben privat und in Deutschland.';
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
                'cashback-description-both',
                'cashback-description-first',
                'cashback-description-standard',
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
            wp_verify_nonce('_wpnonce');
            if (isset($_POST['woocommerce_flizpay_flizpay_api_key'])) {
                $api_key = sanitize_text_field(wp_unslash($_POST['woocommerce_flizpay_flizpay_api_key']));

                if ($api_key !== $this->get_option('flizpay_api_key')) {
                    $webhook_key = $this->get_webhook_key($api_key);
                    $webhook_url = $this->generate_webhook_url($api_key);

                    $this->update_option('enabled', 'no');
                    $this->update_option('flizpay_enabled', 'no');
                    $this->update_option('flizpay_webhook_alive', 'no');

                    if ($webhook_key && $webhook_url) {
                        $this->update_option('flizpay_webhook_key', $webhook_key);
                        $this->update_option('flizpay_webhook_url', $webhook_url);
                        $this->update_option('flizpay_api_key', $api_key);
                    } else {
                        $this->update_option('flizpay_api_key', '');
                    }
                }

                if (isset($_POST['woocommerce_flizpay_flizpay_display_logo'])) {
                    $display_logo = sanitize_text_field(wp_unslash($_POST['woocommerce_flizpay_flizpay_display_logo']));
                    $this->update_option('flizpay_display_logo', $display_logo ? 'yes' : 'no');
                } else {
                    $this->update_option('flizpay_display_logo', 'no');
                }

                if (isset($_POST['woocommerce_flizpay_flizpay_display_description'])) {
                    $display_description = sanitize_text_field(wp_unslash($_POST['woocommerce_flizpay_flizpay_display_description']));
                    $this->update_option('flizpay_display_description', $display_description ? 'yes' : 'no');
                } else {
                    $this->update_option('flizpay_display_description', 'no');
                }

                if (isset($_POST['woocommerce_flizpay_flizpay_display_headline'])) {
                    $display_headline = sanitize_text_field(wp_unslash($_POST['woocommerce_flizpay_flizpay_display_headline']));
                    $this->update_option('flizpay_display_headline', $display_headline ? 'yes' : 'no');
                } else {
                    $this->update_option('flizpay_display_headline', 'no');
                }

                if (isset($_POST['woocommerce_flizpay_flizpay_enable_express_checkout'])) {
                    $enable_express_checkout = sanitize_text_field(wp_unslash($_POST['woocommerce_flizpay_flizpay_enable_express_checkout']));
                    $this->update_option('flizpay_enable_express_checkout', $enable_express_checkout ? 'yes' : 'no');
                } else {
                    $this->update_option('flizpay_enable_express_checkout', 'no');
                }

                if (isset($_POST['woocommerce_flizpay_flizpay_express_checkout_pages'])) {
                    $express_checkout_pages = array_map('sanitize_text_field', wp_unslash($_POST['woocommerce_flizpay_flizpay_express_checkout_pages']));
                    $this->update_option('flizpay_express_checkout_pages', $express_checkout_pages ?? array());
                } else {
                    $this->update_option('flizpay_express_checkout_pages', array());
                }

                if (isset($_POST['woocommerce_flizpay_flizpay_express_checkout_theme'])) {
                    $express_checkout_theme = sanitize_text_field(wp_unslash($_POST['woocommerce_flizpay_flizpay_express_checkout_theme']));
                    $this->update_option('flizpay_express_checkout_theme', $express_checkout_theme ?? 'light');
                } else {
                    $this->update_option('flizpay_express_checkout_theme', 'light');
                }
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
        public function get_webhook_key(string $api_key)
        {
            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('generate_webhook_key', null, false);

            $webhookKey = null;

            if (isset($response['webhookKey'])) {
                $webhookKey = $response['webhookKey'];
            }

            return $webhookKey;
        }

        /**
         * Uses the FLIZ API class to register a Webhook URL to the merchant
         * @param string $api_key
         * @return string
         * 
         * @since 1.0.0
         */
        public function generate_webhook_url(string $api_key)
        {
            $webhookUrl = home_url('/flizpay-webhook?flizpay-webhook=1&source=woocommerce');

            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('save_webhook_url', array('webhookUrl' => $webhookUrl), false);

            $webhookUrlResponse = null;

            if (isset($response['webhookUrl'])) {
                $webhookUrlResponse = $response['webhookUrl'];
            }

            if (strcmp($webhookUrlResponse, $webhookUrl) !== 0) {
                return null;
            }

            return $webhookUrlResponse;
        }

        /**
         * Obtain the current cashback value of the merchant from the transient
         * or by making an API call using the FLIZ API class when the transient 
         * is expired.
         * 
         * @return array | null
         * 
         * @since 1.0.0
         */
        public function get_cashback_data()
        {
            $cashback_data = get_transient('flizpay_cashback_transient');

            if (gettype($cashback_data) === "string") {
                $cashback_data = false;
            }

            if ($cashback_data === false && !empty($this->api_key)) {

                $client = WC_Flizpay_API::get_instance($this->api_key);

                $response = $client->dispatch('fetch_cashback_data', null, false);

                if (isset($response['cashbacks']) && count($response['cashbacks']) > 0) {
                    foreach ($response['cashbacks'] as $cashback) {
                        $firstPurchaseAmount = floatval($cashback['firstPurchaseAmount']);
                        $amount = floatval($cashback['amount']);

                        if ($cashback['active'] && $firstPurchaseAmount > 0 || $amount > 0) {
                            set_transient(
                                'flizpay_cashback_transient',
                                array(
                                    'first_purchase_amount' => $firstPurchaseAmount,
                                    'standard_amount' => $amount
                                ),
                                600
                            );
                            return array(
                                'first_purchase_amount' => $firstPurchaseAmount,
                                'standard_amount' => $amount
                            );
                        } else {
                            set_transient('flizpay_cashback_transient', 0, 600);
                            return null;
                        }
                    }
                } else {
                    set_transient('flizpay_cashback_transient', 0, 600);
                    return null;
                }
            }

            return !empty($cashback_data) &&
                (
                    floatval($cashback_data['first_purchase_amount']) > 0 ||
                    floatval($cashback_data['standard_amount']) > 0
                ) ? $cashback_data : null;
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

                $signedData = hash_hmac('sha256', wp_json_encode($data, JSON_UNESCAPED_UNICODE), $key);

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
                        $this->update_option('enabled', 'yes');
                        wp_send_json_success(array('alive' => true), 200);
                    } else if (isset($data['shippingInfo'])) {
                        // Receive customer shipping address and respond with available methods
                        $shipping_info = $this->calculate_shipping($data);

                        wp_send_json_success($shipping_info, 200);
                    } else if (isset($data['shippingMethodId'])) {
                        // Customer picked a shipping method in express checkout
                        $total_cost = $this->set_shipping_method($data);

                        wp_send_json_success(array('totalCost' => $total_cost), 200);
                    } else {
                        // Transaction Finished (Completed or Failed)
                        $this->process_webhook_data($data);

                        wp_send_json_success('Order updated successfully', 200);
                    }
                } else {
                    wp_send_json_error('Invalid Request', 422);
                }
            }

            return; // Do not process the request
        }

        /**
         * Function responsible for calculating the shipping fees and available methods
         * given the customer address
         * 
         * @param array $data
         * @return mixed
         * 
         * @since 2.0.0
         */
        public function calculate_shipping($data)
        {
            if (!isset($data['orderId']) || !isset($data['shippingInfo'])) {
                return wp_send_json_error('OrderId and Shipping Info are required', 400);
            }

            $order_id = $data['orderId'];
            $shipping_info = $data['shippingInfo'];

            // Extract shipping information
            $city = $shipping_info['city'];
            $street = $shipping_info['street'];
            $zip_code = $shipping_info['zipCode'];
            $number = $shipping_info['number'];
            $firstName = $shipping_info['firstName'];
            $lastName = $shipping_info['lastName'];
            $email = $shipping_info['email'];

            $order = wc_get_order($order_id);
            if (!$order) {
                return wp_send_json_error('Invalid order ID', 404);
            }

            // Update shipping address in the order
            $address = [
                'first_name' => $firstName, // Retain the existing first name
                'last_name' => $lastName,  // Retain the existing last name
                'company' => '',    // Retain the existing company
                'address_1' => $street . ' ' . $number,
                'address_2' => '',
                'city' => $city,
                'state' => '', // Optional: Set state if available
                'postcode' => $zip_code,
                'country' => 'DE',
                'email' => $email
            ];
            $order->set_address($address, 'shipping');
            $order->set_address($address, 'billing');
            $order->set_billing_first_name($firstName);
            $order->set_billing_last_name($lastName);
            $order->set_billing_email($email);
            $order->save();

            $contents = [];

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product(); // Get the WC_Product object
                if ($product) {
                    $contents[$item_id] = [
                        'product_id' => $product->get_id(),
                        'variation_id' => $product->is_type('variable') ? $product->get_id() : 0,
                        'quantity' => $item->get_quantity(),
                        'data' => $product, // WC_Product object
                    ];
                }
            }

            // Calculate available shipping methods
            $package = [
                'destination' => [
                    'country' => 'DE',
                    'state' => '', // Optional: Add state if necessary
                    'postcode' => $zip_code,
                    'city' => $city,
                    'address' => $street . ' ' . $number,
                ],
                'contents' => $contents,
                'contents_cost' => $order->get_total(),
                'applied_coupons' => $order->get_coupon_codes(),
            ];

            // Get available shipping rates
            $prices_include_tax = ('yes' === get_option('woocommerce_prices_include_tax'));

            $shipping = new WC_Shipping();
            $shipping_packages = $shipping->calculate_shipping_for_package($package);

            $available_methods = [];
            foreach ($shipping_packages['rates'] as $rate_id => $rate) {
                // If prices are entered excluding tax, let's add the tax:
                if (!$prices_include_tax) {
                    $tax_rates = WC_Tax::get_shipping_tax_rates();
                    $calculated_taxes = WC_Tax::calc_shipping_tax($rate->get_cost(), $tax_rates);
                    $shipping_cost_incl_tax = (float) $rate->get_cost() + array_sum($calculated_taxes);
                } else {
                    // If prices are entered including tax, assume $rate->get_cost() already has tax
                    $shipping_cost_incl_tax = (float) $rate->get_cost();
                }

                $available_methods[] = [
                    'name' => $rate->get_label(),
                    'totalCost' => $shipping_cost_incl_tax,
                    'id' => $rate_id,
                ];
            }

            return $available_methods;
        }

        /**
         * Function responsible for setting the shipping method of the customer choice
         * 
         * @param array $data
         * @return mixed
         * 
         * @since 2.0.0
         */
        public function set_shipping_method($data)
        {
            if (!isset($data['orderId']) || !isset($data['shippingMethodId'])) {
                return wp_send_json_error('OrderId and ShippingMethodId are required', 400);
            }

            $order_id = $data['orderId'];
            $shipping_method_id = $data['shippingMethodId'];

            $order = wc_get_order($order_id);
            if (!$order) {
                return wp_send_json_error('Invalid order ID', 404);
            }

            $contents = [];

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product(); // Get the WC_Product object
                if ($product) {
                    $contents[$item_id] = [
                        'product_id' => $product->get_id(),
                        'variation_id' => $product->is_type('variable') ? $product->get_id() : 0,
                        'quantity' => $item->get_quantity(),
                        'data' => $product, // WC_Product object
                    ];
                }
            }
            // Calculate available shipping methods
            $package = [
                'destination' => [
                    'country' => $order->get_shipping_country(),
                    'state' => $order->get_shipping_state(),
                    'postcode' => $order->get_shipping_postcode(),
                    'city' => $order->get_shipping_city(),
                ],
                'contents' => $contents,
                'contents_cost' => $order->get_total(),
                'applied_coupons' => $order->get_coupon_codes(),
            ];

            // Get available shipping rates
            $shipping = new WC_Shipping();
            $shipping_packages = $shipping->calculate_shipping_for_package($package);

            // Find the selected method
            $shipping_cost_incl_tax = 0;
            $selected_method = null;
            foreach ($shipping_packages['rates'] as $rate_id => $rate) {
                if ($rate_id === $shipping_method_id) {
                    $tax_rates = WC_Tax::get_shipping_tax_rates();
                    $calculated_taxes = WC_Tax::calc_shipping_tax($rate->get_cost(), $tax_rates);
                    $shipping_cost_incl_tax = (float) $rate->get_cost() + array_sum($calculated_taxes);
                    $selected_method = $rate;
                    break;
                }
            }

            if (!$selected_method) {
                return wp_send_json_error('Invalid shipping method ID', 400);
            }

            // Remove existing shipping items and add the selected method
            $order->remove_order_items('shipping');

            $item = new WC_Order_Item_Shipping();
            $item->set_method_id($selected_method->get_method_id());
            $item->set_method_title($selected_method->get_label());
            $item->set_total($shipping_cost_incl_tax);
            $item->save();

            $order->add_item($item);

            // Recalculate and save order totals
            $order->calculate_totals();
            $order->save();

            return $order->get_total();
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
                $cashback_value = (float) ($fliz_discount * 100) / $data['originalAmount'];

                if ($fliz_discount > 0) {
                    $line_items = $order->get_items();
                    $shipping_items = $order->get_items('shipping');

                    // Apply additional discount to line items
                    foreach ($line_items as $item) {
                        $item_subtotal = $item->get_total();

                        // Calculate the additional discount based on the already discounted total
                        $discount_amount_fliz = ($item_subtotal * $cashback_value) / 100;

                        // Set the new total for the line item after applying the additional discount
                        $new_total = round($item_subtotal - $discount_amount_fliz, 2, PHP_ROUND_HALF_DOWN);
                        $item->set_total($new_total);

                        // Save the item changes
                        $item->save();
                    }

                    // Apply additional discount to shipping items
                    foreach ($shipping_items as $shipping) {
                        $shipping_total = $shipping->get_total();

                        // Calculate the additional discount for shipping
                        $discount_amount_fliz = ($shipping_total * $cashback_value) / 100;

                        // Set the new shipping total after applying the discount
                        $new_shipping_total = round($shipping_total - $discount_amount_fliz, 2, PHP_ROUND_HALF_DOWN);
                        // Reset item taxes to let WC recalculate
                        $shipping->set_total($new_shipping_total);
                        // Save the shipping item changes
                        $shipping->save();
                    }

                    // Recalculate the overall order totals
                    $order->calculate_taxes();
                    $order->calculate_totals();
                    $order->set_total($data['amount']);
                    $order->add_order_note('FLIZ Cashback Applied: ' . $data['currency'] . sanitize_text_field($fliz_discount));
                    $order->save();
                    WC()->cart->empty_cart();
                }

                if (isset($data['transactionId'])) {
                    $order->add_order_note('FLIZ transaction ID: ' . sanitize_text_field($data['transactionId']));
                }

                // Send customer and shop owner e-mails about the order
                $mailer = WC()->mailer();
                $emails = $mailer->get_emails();

                if (!empty($emails['WC_Email_Customer_Completed_Order'])) {
                    $emails['WC_Email_Customer_Completed_Order']->trigger($order_id);
                }
                if (!empty($emails['WC_Email_Customer_Invoice'])) {
                    $emails['WC_Email_Customer_Invoice']->trigger($order_id);
                }
                if (!empty($emails['WC_Email_New_Order'])) {
                    $emails['WC_Email_New_Order']->trigger($order_id);
                }

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
         * @param string $source
         * @return array
         * 
         * @since 1.0.0
         */
        public function process_payment($order_id, $source = 'plugin')
        {
            $order = wc_get_order($order_id);
            // We set the status as draft here to avoid accountability softwares picking the Pending Payment order
            $order->set_status('wc-checkout-draft', 'Waiting FLIZpay payment');
            $order->save();

            $redirectUrl = $this->create_transaction($order, $source);

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
         * @param string $source
         * @return string
         * 
         * @since 1.0.0
         */
        public function create_transaction($order, $source = 'plugin')
        {
            $flizpay_setting = get_option('woocommerce_flizpay_settings');
            $api_key = $flizpay_setting['flizpay_api_key'];
            $customer = array(
                'email' => $order->get_billing_email(),
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name()
            );
            $products = array();
            foreach ( $order->get_items() as $item_id => $item ) {
                $product = $item->get_product();
                if ( !$product ) {
                    continue; 
                }
  
                $name    = $product->get_name();
                $amount  = $item->get_quantity();
                $price   = $product->get_price(); 
        
                $products[] = array(
                    'name'    => $name,
                    'amount'  => $amount,
                    'price'   => $price,
                );
            }
            $body = array(
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                'externalId' => $order->get_id(),
                'successUrl' => $order->get_checkout_order_received_url(),
                'failureUrl' => 'https://checkout.flizpay.de/failed',
                'customer' => $customer,
                'source' => $source,
                'products' => $products,  
            );
            $client = WC_Flizpay_API::get_instance($api_key);

            $response = $client->dispatch('create_transaction', $body, false);

            return $response['redirectUrl'];
        }

        /**
         * Handler function for flizpay express checkout
         * @return never
         * @since 2.0.0
         */
        public function flizpay_express_checkout(): never
        {
            check_ajax_referer('express_checkout_nonce', 'nonce');

            if (!isset($_POST['cart'])) {
                $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
                $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
                $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;

                if (!$product_id || $quantity <= 0) {
                    echo esc_html(wp_send_json_error(['message' => 'Invalid product or quantity.']));
                    die();
                }

                // Clear the current cart
                WC()->cart->empty_cart();

                // Add product to cart
                $new_cart = $variation_id
                    ? WC()->cart->add_to_cart($product_id, $quantity, $variation_id)
                    : WC()->cart->add_to_cart($product_id, $quantity);


                if (!$new_cart) {
                    echo esc_html(wp_send_json_error(['message' => 'Unable to add product to cart.']));
                    die();
                }
            }

            // Create order from cart
            $order_id = wc_create_order();
            $order = wc_get_order($order_id);

            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $item = new WC_Order_Item_Product();

                $item->set_props(array(
                    'product_id' => $cart_item['product_id'],
                    'variation_id' => $cart_item['variation_id'],
                    'quantity' => $cart_item['quantity'],

                    // Use the cart's exact line subtotals/line totals (reflects discounts & sale prices)
                    'subtotal' => $cart_item['line_subtotal'],
                    'total' => $cart_item['line_total'],
                    'subtotal_tax' => $cart_item['line_subtotal_tax'],
                    'total_tax' => $cart_item['line_tax'],
                    'taxes' => $cart_item['line_tax_data'],
                ));

                $order->add_item($item);
            }

            // Remove shipping
            foreach ($order->get_items('shipping') as $item_id => $shipping_item) {
                $order->remove_item($item_id);
            }
            $order->calculate_totals();
            $order->update_status('wc-checkout-draft', 'FLIZpay Express Checkout initiated.');
            $order->save();

            echo esc_html(wp_send_json_success(
                $this->process_payment($order->get_id(), 'express_checkout')
            ));
            die();
        }
    }
}