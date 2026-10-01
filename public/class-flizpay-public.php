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

    /** Placement slot → the merchant setting that switches it on. */
    public const PLACEMENT_SETTINGS = array(
        'listing-item' => 'flizpay_placement_listing',
        'product-price' => 'flizpay_placement_product',
        'product-page' => 'flizpay_placement_product',
        'cart' => 'flizpay_placement_cart',
        'mini-cart' => 'flizpay_placement_cart',
        'checkout' => 'flizpay_placement_checkout',
        'order-received' => 'flizpay_placement_order_received',
    );

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
     * Load the hosted placement script wherever placements are enabled. The Cart and Checkout
     * blocks have no template hooks, so there a small slot-fill script carries the slot.
     */
    public function enqueue_placement_scripts()
    {
        if (!$this->placements_enabled()) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . '-placement',
            // FLIZPAY_PLACEMENT_SCRIPT_URL (wp-config.php) points at a staging build.
            defined('FLIZPAY_PLACEMENT_SCRIPT_URL') ? FLIZPAY_PLACEMENT_SCRIPT_URL : 'https://app.flizpay.de/web-components/v1/flizpay.js',
            array(),
            null,
            array('strategy' => 'async')
        );

        $block_slot = $this->block_checkout_slot();
        if ($block_slot !== null && $this->slot_enabled($block_slot)) {
            wp_enqueue_script(
                $this->plugin_name . '-placement-fill',
                plugin_dir_url(__FILE__) . 'js/flizpay-placement-fill.js',
                array('wp-plugins', 'wp-element', 'wc-blocks-checkout'),
                $this->version,
                true
            );
            wp_add_inline_script(
                $this->plugin_name . '-placement-fill',
                'window.flizpayPlacement = ' . wp_json_encode($this->placement_attributes($block_slot, $this->cart_attributes())) . ';',
                'before'
            );
        }
    }

    /**
     * The script sets no cookies or storage; tell consent managers not to block it.
     */
    public function placement_script_tag($tag, $handle)
    {
        if ($handle !== $this->plugin_name . '-placement') {
            return $tag;
        }

        return str_replace('<script ', '<script data-cookieconsent="ignore" data-borlabs-cookie-script-blocker-ignore ', $tag);
    }

    public function render_listing_placement()
    {
        if (!wp_is_block_theme()) {
            echo $this->placement_html('listing-item', $this->product_attributes()); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in placement_html()
        }
    }

    public function render_product_price_placement()
    {
        if (!wp_is_block_theme()) {
            echo $this->placement_html('product-price', $this->product_attributes()); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in placement_html()
        }
    }

    public function render_product_placement()
    {
        if (!wp_is_block_theme()) {
            echo $this->product_placement_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in placement_html()
        }
    }

    public function render_cart_placement()
    {
        echo $this->placement_html('cart', $this->cart_attributes()); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in placement_html()
    }

    public function render_mini_cart_placement()
    {
        echo $this->placement_html('mini-cart', $this->cart_attributes()); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in placement_html()
    }

    public function render_checkout_placement()
    {
        echo $this->placement_html('checkout', $this->cart_attributes()); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in placement_html()
    }

    public function render_order_received_placement($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        echo $this->placement_html('order-received', array( // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in placement_html()
            'amount' => $this->minor_units($order->get_total()),
            'currency' => $order->get_currency(),
        ));
    }

    public function append_price_placement($block_content, $block, $instance)
    {
        if (!wp_is_block_theme()) {
            return $block_content;
        }

        $product = wc_get_product($instance->context['postId'] ?? 0);
        if (!$product) {
            return $block_content;
        }

        // On its own page the main product's price block is the product-price slot, anywhere else a listing item.
        $is_main_product = function_exists('is_product') && is_product() && $product->get_id() === get_queried_object_id();

        return $block_content . $this->placement_html($is_main_product ? 'product-price' : 'listing-item', $this->product_attributes($product));
    }

    public function append_product_placement($block_content)
    {
        if (!wp_is_block_theme()) {
            return $block_content;
        }

        return $block_content . $this->product_placement_html();
    }

    private function product_placement_html(): string
    {
        static $rendered = false;

        if ($rendered || !function_exists('is_product') || !is_product()) {
            return '';
        }

        $rendered = true;

        return $this->placement_html('product-page', $this->product_attributes());
    }

    private function placement_html(string $slot, array $context = array()): string
    {
        if (!$this->slot_enabled($slot)) {
            return '';
        }

        $attributes = '';
        foreach ($this->placement_attributes($slot, $context) as $name => $value) {
            $attributes .= sprintf(' data-%s="%s"', $name, esc_attr((string) $value));
        }

        return '<fliz-placement' . $attributes . '></fliz-placement>';
    }

    /**
     * Everything the page knows travels with the slot; the script uses what the current layouts need.
     */
    private function placement_attributes(string $slot, array $context = array()): array
    {
        return array_merge(array(
            'public-id' => $this->settings['flizpay_public_id'],
            'slot' => $slot,
            'locale' => get_locale(),
            'currency' => get_woocommerce_currency(),
            'platform' => 'woocommerce',
            'plugin-version' => $this->version,
        ), $context);
    }

    private function product_attributes($product = null): array
    {
        $product = $product ?: ($GLOBALS['product'] ?? null);
        if (!$product instanceof WC_Product) {
            return array();
        }

        $attributes = array('product-id' => $product->get_id());
        if ($product->get_price() !== '') {
            $attributes['amount'] = $this->minor_units($product->get_price());
        }

        return $attributes;
    }

    private function cart_attributes(): array
    {
        if (!function_exists('WC') || !WC()->cart) {
            return array();
        }

        return array('amount' => $this->minor_units(WC()->cart->get_total('edit')));
    }

    /**
     * Amounts travel in minor units (cents), like other on-site messaging SDKs.
     */
    private function minor_units($amount): int
    {
        return (int) round((float) $amount * pow(10, wc_get_price_decimals()));
    }

    private function block_checkout_slot(): ?string
    {
        if (function_exists('is_cart') && is_cart() && has_block('woocommerce/cart')) {
            return 'cart';
        }
        if (function_exists('is_checkout') && is_checkout() && !is_wc_endpoint_url('order-received') && has_block('woocommerce/checkout')) {
            return 'checkout';
        }

        return null;
    }

    private function placements_enabled(): bool
    {
        return is_array($this->settings)
            && ($this->settings['enabled'] ?? 'no') === 'yes'
            && !empty($this->settings['flizpay_public_id'])
            && in_array('yes', array_intersect_key($this->settings, array_flip(self::PLACEMENT_SETTINGS)), true);
    }

    private function slot_enabled(string $slot): bool
    {
        $setting = self::PLACEMENT_SETTINGS[$slot] ?? null;

        return $setting !== null && $this->placements_enabled() && ($this->settings[$setting] ?? 'no') === 'yes';
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
     * Declare extension compatibilities on before_woocommerce_init hook
     */
    public function declare_compatibilities()
    {
        $this->declare_cart_checkout_blocks_compatibility();
        $this->declare_high_performance_order_storage_compatibility();
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

    /**
     * Custom function to declare compatibility with cart checkout blocks feature
     */
    private function declare_cart_checkout_blocks_compatibility()
    {
        // Check if the required class exists
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare compatibility for 'cart_checkout_blocks'
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', $this->plugin_file(), true);
        }
    }

    /**
     * Custom function to declare compatibility with HPOS feature
     */
    private function declare_high_performance_order_storage_compatibility()
    {
        // Check if the required class exists
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', $this->plugin_file(), true);
        }
    }

    /**
     * Absolute path to the plugin entry file.
     *
     * WooCommerce matches compatibility declarations against the installed plugin
     * list by this path, so passing any other file makes the declaration a no-op.
     *
     * @return string
     */
    private function plugin_file()
    {
        return defined('FLIZPAY_PLUGIN_FILE') ? FLIZPAY_PLUGIN_FILE : dirname(__DIR__) . '/flizpay.php';
    }
}
