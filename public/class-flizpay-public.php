<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.flizpay.de
 * @since      1.0.0
 *
 * @package    Flizpay
 * @subpackage Flizpay/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Flizpay
 * @subpackage Flizpay/public
 * @author     Flizpay <carlos.cunha@flizpay.de>
 */
class Flizpay_Public
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Flizpay_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Flizpay_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/flizpay-public.css', array(), $this->version, 'all');

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Flizpay_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Flizpay_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/flizpay-public.js',
            array('jquery', 'woocommerce', 'wc-cart-fragments', 'wc-checkout'),
            $this->version,
            false
        );

        $ajaxurl = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'public_dir_path' => plugin_dir_url(__FILE__)
        );
        wp_localize_script($this->plugin_name, "flizpay_frontend", $ajaxurl);

        wp_enqueue_script($this->plugin_name . '_jquerymin', plugin_dir_url(__FILE__) . 'js/googleapi.jquery.min.js', array('jquery'), $this->version, false);

    }

    /**
     * @return void
     * @throws Exception
     */
    public function flizpay_get_payment_data()
    {
        $form = WC()->session->get('customer');

        $chosen_shipping_methods = WC()->session->get("chosen_shipping_methods");
        $shipping_method = $this->get_shipping_method($chosen_shipping_methods[0]);

        $checkout = WC()->checkout();
        $order_id = $checkout->create_order(array());
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Cant create order'], 422);
        }

        $order->set_address($this->get_order_address('billing', $form));
        $order->set_address($this->get_order_address('shipping', $form), 'shipping');

        $payment_gateways = WC()->payment_gateways->payment_gateways();
        $order->set_payment_method($payment_gateways['flizpay']);

        // Get a new instance of the WC_Order_Item_Shipping Object
        $item = new WC_Order_Item_Shipping();

        $item->set_method_title($shipping_method['title']);
        $item->set_method_id($chosen_shipping_methods[0]);
        $item->set_total($shipping_method['cost']);
        $order->add_item($item);

        $order->calculate_totals();
        $order->update_status('pending');

        $payment_info = $this->get_order_payment_data($order);
        echo json_encode($payment_info);
        wp_die();
    }

    /**
     * @param $key
     * @param $post_data
     * @return array
     */
    public function get_order_address($key, $post_data)
    {
        return array(
            'first_name' => $post_data[$key . '_first_name'] ?? $post_data['shipping_first_name'],
            'last_name' => $post_data[$key . '_last_name'] ?? $post_data['shipping_last_name'],
            'company' => $post_data[$key . '_company'] ?? $post_data['shipping_company'],
            'email' => $post_data['email'],
            'phone' => $post_data[$key . '_phone'] ?? $post_data['shipping_phone'],
            'address_1' => $post_data[$key . '_address_1'] ?? $post_data['shipping_address_1'],
            'address_2' => $post_data[$key . '_address_2'] ?? $post_data['shipping_address_2'],
            'city' => $post_data[$key . '_city'] ?? $post_data['shipping_city'],
            'state' => $post_data[$key . '_state'] ?? $post_data['shipping_state'],
            'postcode' => $post_data[$key . '_postcode'] ?? $post_data['shipping_postcode'],
            'country' => $post_data[$key . '_country'] ?? $post_data['shipping_country']
        );
    }

    /**
     * @param $chosen_shipping_methods
     * @return array|null
     */
    public function get_shipping_method($chosen_shipping_methods)
    {
        if (preg_match("/\d+/", $chosen_shipping_methods, $instance_id)) {
            $shipping_method = WC_Shipping_Zones::get_shipping_method($instance_id[0]);

            return array(
                'title' => $shipping_method->title,
                'cost' => $shipping_method->cost
            );
        }
        return null;
    }

    /**
     * @param $order
     * @return array
     */
    public function get_order_payment_data($order)
    {
        $flizpay_setting = get_option('woocommerce_flizpay_settings');
        $api_key = $flizpay_setting['flizpay_api_key'];
        $body = array(
            'amount' => $order->get_total(),
            'currency' => $order->get_currency(),
            'externalId' => $order->get_id(),
            'successUrl' => null,
            'failureUrl' => null,
        );

        $client = WC_Flizpay_API::get_instance($api_key);

        $response = $client->dispatch('create_transaction', $body);

        return array('callback_url' => $response['redirectUrl'], 'order_id' => $order->get_id());
    }

    /**
     * @return void
     */
    public function flizpay_order_finish()
    {
        $order_id = $_POST['order_id'];
        $order = wc_get_order($order_id);
        $status = $order->get_status();
        echo json_encode(
            array(
                'status' => $status,
                'url' => $status === 'processing' ? $order->get_checkout_order_received_url() : get_home_url() . '/flizpay-payment-fail',
            )
        );
        die;
    }

    /**
     * Custom function to declare compatibility with cart checkout blocks feature
     * Custom function to declare compatibility with cart_checkout_blocks feature
     */
    public function declare_cart_checkout_blocks_compatibility()
    {
        // Check if the required class exists
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare compatibility for 'cart_checkout_blocks'
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }

    /**
     * Custom function to register a payment method type
     */
    public function flizpay_reg_order_payment_method_type()
    {
        // Check if the required class exists
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        //Include the custom Blocks Checkout class
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/flizpay-gateway-blocks.php';

        // Hook the registration function to the action 'woocommerce blocks_payment method_type_registration'
        add_action('woocommerce_blocks_payment_method_type_registration', function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register(new Flizpay_Gateway_Blocks);
        });
    }

}
