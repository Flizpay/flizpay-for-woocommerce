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
 * Defines the plugin name, version, and 
 * enqueue the public-facing JavaScript.
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
     * The FLIZpay settings
     *
     * @since    1.4.0
     * @access   private
     * @var      array    $settings    The plugin settings.
     */
    private $settings;

    /**
     * The FLIZpay assets path
     *
     * @since    1.4.0
     * @access   private
     * @var      string    $assets_url    The current version of this plugin.
     */
    private $assets_url;

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
        $this->settings = get_option("woocommerce_flizpay_settings");
        $this->assets_url = plugins_url() . '/' . basename(dirname(__DIR__)) . '/assets/images';
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name . '-css',
            plugin_dir_url(__FILE__) . 'css/flizpay-public.css',
            array(),
            $this->version,
            false
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     * Only registers it on the checkout page
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        if (!$this->is_checkout_flow_page()) {
            return;
        }

        $this->enqueue_checkout_scripts();
    }

    /**
     * True only on pages where the customer has a known order context —
     * the checkout page, the order-pay endpoint (paying for an existing order
     * via a WC-verified key URL), or the order-received (thank-you) page.
     */
    private function is_checkout_flow_page()
    {
        if (!function_exists('is_checkout')) {
            return false;
        }
        return is_checkout()
            || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-pay'))
            || (function_exists('is_order_received_page') && is_order_received_page());
    }

    // Enqueues the public script for the checkout page
    private function enqueue_checkout_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name . '-globals',
            plugin_dir_url(__FILE__) . 'js/flizpay-globals.js',
            array('jquery', 'wp-element', 'wp-data'),
            $this->version,
            false
        );
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/flizpay-public.js',
            array('jquery', 'wp-element', 'wp-data'),
            $this->version,
            false
        );
        $variables = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'public_dir_path' => plugin_dir_url(__FILE__),
            'order_finish_nonce' => wp_create_nonce('order_finish_nonce'),
            'fliz_logo' => $this->assets_url . '/fliz-logo.svg',
            'fliz_loading_wheel' => $this->assets_url . '/fliz-loading-wheel.svg',
            'cashback' => get_transient('flizpay_cashback_transient'),
            'flizpay_version' => $this->version,
        );
        wp_localize_script($this->plugin_name, "flizpay_frontend", $variables);
    }

    /**
     * Function used by the mobile polling mechanism to check the order status
     * It will then return the redirect URL based on the success or failure of the request.
     * 
     * @return void
     * 
     * @since 1.0.0
     */
    public function flizpay_order_finish()
    {
        check_ajax_referer('order_finish_nonce', 'nonce');

        if (!isset($_POST['order_id'])) {
            wp_send_json_error('Missing order_id', 400);
        }

        $order_id = absint(wp_unslash($_POST['order_id']));
        $order = $order_id ? wc_get_order($order_id) : null;

        // Bind the lookup to the current customer: either a logged-in owner, or the
        // current guest session's "order_awaiting_payment" set during process_payment.
        if (!$order || !$this->customer_can_view_order($order)) {
            wp_send_json_error('Forbidden', 403);
        }

        $status = $order->get_status();

        echo wp_json_encode(
            array(
                'status' => $status,
                'url' => $status === 'processing' ? $order->get_checkout_order_received_url() : 'https://checkout.flizpay.de/failed',
            )
        );
        die;
    }

    /**
     * True only when the request can legitimately ask about this order.
     * Logged-in users must own the order; guests must have the order set as
     * "awaiting payment" in their WC session (this is set by WC during
     * process_payment and by the order-pay endpoint after WC verifies the key).
     */
    private function customer_can_view_order(\WC_Order $order)
    {
        if (is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id()) {
            return true;
        }

        if (!function_exists('WC') || !WC()->session) {
            return false;
        }

        $awaiting = WC()->session->get('order_awaiting_payment');
        return $awaiting && (int) $awaiting === (int) $order->get_id();
    }

    /**
     * Custom function to declare compatibility with cart checkout blocks feature
     */
    public function declare_cart_checkout_blocks_compatibility()
    {
        // Check if the required class exists
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare compatibility for 'cart_checkout_blocks'
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', dirname(__DIR__) . '/flizpay.php', true);
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
            // Register an instance of Flizpay_Gateway_blocks
            $payment_method_registry->register(new Flizpay_Gateway_Blocks);
        });
    }
}
