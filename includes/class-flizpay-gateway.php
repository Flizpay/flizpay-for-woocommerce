<?php
/**
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'flizpay_init_gateway_class' );
function flizpay_init_gateway_class() {

    class WC_Flizpay_Gateway extends WC_Payment_Gateway {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct() {
            $this->id = 'flizpay';
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = 'Flizpay Gateway';
            $this->method_description = 'Flizpay payment gateway for WooCommerce';

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->client_id = $this->get_option( 'flizpay_client_id' );
            $this->iban = $this->get_option( 'flizpay_shop_iban' );


            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields(){
            $this->form_fields = apply_filters( 'flizpay_load_settings', true );
        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields() {

        }

        /**
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts() {

        }

        /**
          * Fields validation, more in Step 5
         */
        public function validate_fields() {

        }

        /**
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order ),
            );
        }

        /**
         * In case you need a webhook, like PayPal IPN etc
         */
        public function webhook() {

        }
    }
}