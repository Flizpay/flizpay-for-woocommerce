<?php
if (!defined('FLIZPAY_VERSION')) {
    define('FLIZPAY_VERSION', '2.4.5');
}

/**
 * The Payment Gateway class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'flizpay_init_gateway_class');
function flizpay_init_gateway_class()
{

    class WC_Flizpay_Gateway extends WC_Payment_Gateway
    {
        public static $VERSION = FLIZPAY_VERSION;

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
        public $flizpay_order_status;
        public $cashback_helper;
        public $webhook_helper;
        public $shipping_helper;
        public $api_service;

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
            require_once plugin_dir_path(__DIR__) . 'includes/class-flizpay-cashback-helper.php';
            require_once plugin_dir_path(__DIR__) . 'includes/class-flizpay-webhook-helper.php';
            require_once plugin_dir_path(__DIR__) . 'includes/class-flizpay-shipping-helper.php';
            require_once plugin_dir_path(__DIR__) . 'includes/class-flizpay-api-service.php';

            $this->i18n = new Flizpay_i18n();
            $this->id = 'flizpay';
            $this->has_fields = true;
            $this->method_title = 'FLIZpay';
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
            $this->flizpay_order_status = $this->get_option('flizpay_order_status');
            // Initialize helper classes
            $this->cashback_helper = new Flizpay_Cashback_Helper($this);
            $this->webhook_helper = new Flizpay_Webhook_Helper($this);
            $this->shipping_helper = new Flizpay_Shipping_Helper($this);
            $this->api_service = new Flizpay_API_Service($this->api_key);

            if ($this->flizpay_display_logo === 'yes') {
                $this->icon = plugins_url() . '/' . basename(dirname(__DIR__)) . '/assets/images/fliz-checkout-logo.svg';
            }

            $this->cashback = $this->get_cashback_data();

            $this->init_gateway_info();

            //Admin options setup handler
            add_action('woocommerce_settings_save_checkout', array($this, 'test_gateway_connection'));

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
            $this->cashback_helper->set_cashback_info();
            $this->cashback_helper->set_title();
            $this->cashback_helper->set_description();
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
            if (!wp_verify_nonce($_REQUEST['_wpnonce'], 'woocommerce-settings')) {
                wp_die('Security check failed');
            }
            if (isset($_POST['woocommerce_flizpay_flizpay_api_key'])) {
                $api_key = sanitize_text_field(wp_unslash($_POST['woocommerce_flizpay_flizpay_api_key']));

                if ($api_key !== $this->get_option('flizpay_api_key') || $this->get_option('flizpay_webhook_alive') === 'no') {
                    $this->api_service = new Flizpay_API_Service($api_key);

                    $this->update_option('enabled', 'no');
                    $this->update_option('flizpay_enabled', 'no');
                    $this->update_option('flizpay_webhook_alive', 'no');
                    usleep(500000); // Sleep for 0.5 seconds to wait for database update
                    $webhook_url = $this->api_service->generate_webhook_url();
                    $webhook_key = $this->api_service->get_webhook_key();
                    $cashback_data = $this->api_service->fetch_cashback_data();

                    if ($webhook_key && $webhook_url) {
                        $this->update_option('flizpay_webhook_key', $webhook_key);
                        $this->update_option('flizpay_webhook_url', $webhook_url);
                        $this->update_option('flizpay_api_key', $api_key);
                        $this->update_option('flizpay_cashback', $cashback_data);
                        $this->cashback = $cashback_data;
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

                if (isset($_POST['woocommerce_flizpay_flizpay_order_status'])) {
                    $flizpay_order_status = sanitize_text_field(wp_unslash($_POST['woocommerce_flizpay_flizpay_order_status']));
                    $this->update_option('flizpay_order_status', $flizpay_order_status ?? 'wc-pending');
                } else {
                    $this->update_option('flizpay_order_status', 'wc-pending');
                }

                $this->init_gateway_info();
            }
        }

        /**
         * Obtain the current cashback value of the merchant from the transient
         * 
         * @return array | null
         * 
         * @since 1.0.0
         */
        public function get_cashback_data()
        {
            $cashback_data = $this->get_option('flizpay_cashback');

            if (gettype($cashback_data) === "string")
                return null;

            if (empty($cashback_data))
                return null;

            if (
                floatval($cashback_data['first_purchase_amount']) > 0 ||
                floatval($cashback_data['standard_amount']) > 0
            )
                return $cashback_data;

            return null;
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
            $this->webhook_helper->register_webhook_endpoint();
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
            $this->webhook_helper->handle_webhook_request();
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
            return $this->shipping_helper->calculate_shipping($data);
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
            return $this->shipping_helper->set_shipping_method($data);
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
            $order->calculate_totals(true);
            $order->update_status($this->flizpay_order_status, 'FLIZpay Checkout initiated. Waiting for payment - ' . $source);
            $order->save();

            $redirectUrl = $this->api_service->create_transaction($order, $source);

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
                $variation_data = isset($_POST['variation_data']) ? sanitize_text_field($_POST['variation_data']) : '';

                // Parse variation data if available
                $variation_attributes = array();
                if (!empty($variation_data) && $variation_id) {
                    $decoded_data = json_decode($variation_data, true);
                    if (is_array($decoded_data)) {
                        foreach ($decoded_data as $key => $value) {
                            // Convert attribute name format (e.g., attribute_pa_color -> pa_color)
                            $attribute_key = str_replace('attribute_', '', $key);
                            $variation_attributes[$attribute_key] = $value;
                        }
                    }
                }

                if (!$product_id || $quantity <= 0) {
                    echo esc_html(wp_send_json_error(['message' => 'Invalid product or quantity.']));
                    die();
                }

                // Clear the current cart
                WC()->cart->empty_cart();

                // Add product to cart with variation data if applicable
                $new_cart = $variation_id
                    ? WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_attributes)
                    : WC()->cart->add_to_cart($product_id, $quantity);


                if (!$new_cart) {
                    echo esc_html(wp_send_json_error(['message' => 'Unable to add product to cart.']));
                    die();
                }
            }

            // Create order from cart
            $order_id = wc_create_order();
            $order = wc_get_order($order_id);

            // Set payment method explicitly for express checkout orders
            $order->set_payment_method('flizpay');
            $order->set_payment_method_title('FLIZpay');

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
            $order->calculate_totals(true);
            $order->save();

            echo esc_html(wp_send_json_success(
                $this->process_payment($order->get_id(), 'express_checkout')
            ));
            die();
        }
    }
}