<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * The Blocks compatible implementation of the gateway
 */
final class Flizpay_Gateway_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'flizpay';

    /**
     * Initialize the gateway itself and load the seetings
     * 
     * @return void
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_flizpay_settings', []);
        $this->gateway = new WC_Flizpay_Gateway();
    }

    /**
     * Relies on the gateway availability to decide whether it's active or not
     * 
     * @return bool
     */
    public function is_active()
    {
        return $this->gateway->is_available();
    }

    /**
     * Register the checkout blocks script
     * 
     * @return string[]
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'flizpay-blocks-integration',
            plugin_dir_url(__FILE__) . 'js/checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                //                'wp-il8n'
            ],
            $this->gateway::$VERSION,
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('flizpay-blocks-integration');
        }

        return ['flizpay-blocks-integration'];
    }

    /**
     *  Make the title, description and enabled status available to the blocks state manager
     * @return array
     */
    public function get_payment_method_data()
    {
        return [
            'enabled' => $this->gateway->is_available(),
            'title' => $this->gateway->title,
            'description' => $this->gateway->description
        ];
    }
}