<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Flizpay_Gateway_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'flizpay';

    /**
     * @return void
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_flizpay_settings', []);
        $this->gateway = new WC_Flizpay_Gateway();
    }

    /**
     * @return bool
     */
    public function is_active()
    {
        return $this->gateway->is_available();
    }

    /**
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

    public function get_payment_method_data()
    {
        return [
            'enabled' => $this->gateway->is_available(),
            'title' => $this->gateway->title,
            'description' => $this->gateway->description
        ];
    }
}