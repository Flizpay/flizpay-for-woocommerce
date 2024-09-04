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
        if (is_checkout()) {
            wp_enqueue_script(
                $this->plugin_name,
                plugin_dir_url(__FILE__) . 'js/flizpay-public.js',
                array('jquery', 'wp-element', 'wp-data'),
                $this->version,
                false
            );

            $ajaxurl = array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'public_dir_path' => plugin_dir_url(__FILE__),
                'order_finish_nonce' => wp_create_nonce('order_finish_nonce'),
            );
            wp_localize_script($this->plugin_name, "flizpay_frontend", $ajaxurl);
        }
    }

    /**
     * @return void
     */
    public function flizpay_order_finish()
    {
        check_ajax_referer('order_finish_nonce', 'nonce');
        if (isset($_POST['order_id'])) {
            $order_id = sanitize_text_field(wp_unslash($_POST['order_id']));
            $order = wc_get_order($order_id);
            $status = $order->get_status();
            echo wp_json_encode(
                array(
                    'status' => $status,
                    'url' => $status === 'processing' ? $order->get_checkout_order_received_url() : get_home_url() . '/flizpay-payment-fail',
                )
            );
        }
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
