<?php

declare(strict_types=1);


if (!defined('FLIZPAY_VERSION')) {
    define('FLIZPAY_VERSION', '2.6.0');
}

/**
 * The Payment Gateway class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'flizpay_init_gateway_class');
function flizpay_init_gateway_class()
{

    class WC_Flizpay_Gateway extends WC_Payment_Gateway
    {
        public static string $VERSION = FLIZPAY_VERSION;

        public array|null $cashback;
        public Flizpay_i18n $i18n;
        public string $api_key;
        public string $webhook_key;
        public string $webhook_url;
        public string $flizpay_webhook_alive;
        public string $flizpay_display_logo;
        public string $flizpay_display_description;
        public string $flizpay_display_headline;
        public string $flizpay_order_status;
        public string $flizpay_sentry_enabled;
        public string $flizpay_restrict_to_germany;
        public Flizpay_Cashback_Helper $cashback_helper;
        public Flizpay_Webhook_Helper $webhook_helper;
        public Flizpay_API_Service $api_service;
        public Flizpay_Settlement $settlement;

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
            require_once plugin_dir_path(__DIR__) . 'includes/class-flizpay-api-service.php';
            require_once plugin_dir_path(__DIR__) . 'includes/class-flizpay-settlement.php';

            $this->i18n = new Flizpay_i18n();
            $this->id = 'flizpay';
            $this->has_fields = true;
            $this->method_title = 'FLIZpay';
            $this->method_description = 'FLIZpay Plugin WooCommerce';

            // Method with all the options fields
            $this->init_form_fields();
            // Load the setting.
            $this->init_settings();
            $this->maybe_migrate_legacy_enabled_setting();
            // Ensure text domain is loaded
            $this->i18n->load_plugin_textdomain();

            $this->enabled = $this->get_option('enabled', 'yes');
            $this->api_key = $this->get_option('flizpay_api_key');
            $this->webhook_key = $this->get_option('flizpay_webhook_key');
            $this->webhook_url = $this->get_option('flizpay_webhook_url');
            $this->flizpay_webhook_alive = $this->get_option('flizpay_webhook_alive');
            $this->flizpay_display_logo = $this->get_option('flizpay_display_logo');
            $this->flizpay_display_description = $this->get_option('flizpay_display_description');
            $this->flizpay_display_headline = $this->get_option('flizpay_display_headline');
            $this->flizpay_order_status = $this->get_option('flizpay_order_status');
            $this->flizpay_sentry_enabled = $this->get_option('flizpay_sentry_enabled');
            $this->flizpay_restrict_to_germany = $this->get_option('flizpay_restrict_to_germany');
            // Initialize helper classes
            $this->cashback_helper = new Flizpay_Cashback_Helper($this);
            $this->settlement = new Flizpay_Settlement();
            $this->webhook_helper = new Flizpay_Webhook_Helper($this, $this->settlement);
            $this->api_service = new Flizpay_API_Service($this->api_key);

            if ($this->flizpay_display_logo === 'yes') {
                $this->icon = plugins_url() . '/' . basename(dirname(__DIR__)) . '/assets/images/fliz-checkout-logo.svg';
            }

            $this->cashback = $this->get_cashback_data();

            $this->init_gateway_info();

            // Admin options setup handler.
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Webhook handler
            add_action('init', array($this, 'register_webhook_endpoint'));
            add_action('template_redirect', array($this, 'handle_webhook_request'));

            // Order placed e-mail handler
            add_filter('woocommerce_email_enabled_new_order', array($this, 'disable_new_order_email_for_flizpay'), 10, 2);
        }

        /**
         * Migrate existing stores from the old auto-managed flizpay_enabled flag
         * to WooCommerce's standard merchant-controlled enabled setting.
         *
         * @return void
         */
        private function maybe_migrate_legacy_enabled_setting(): void
        {
            if (!is_array($this->settings) || array_key_exists('enabled', $this->settings)) {
                return;
            }

            if (!array_key_exists('flizpay_enabled', $this->settings)) {
                return;
            }

            $this->update_option('enabled', $this->settings['flizpay_enabled'] === 'yes' ? 'yes' : 'no');
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
                // If FLIZ order is not yet paid, disable the New Order email
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
        public function init_gateway_info(): void
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
         * @return bool
         *
         * @since 1.0.0
         */
        public function process_admin_options(): bool
        {
            if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'woocommerce-settings')) {
                wp_die('Security check failed');
            }

            $previous_api_key = $this->get_option('flizpay_api_key');
            $saved = parent::process_admin_options();
            $this->init_settings();

            $api_key = trim((string) $this->get_option('flizpay_api_key'));
            $this->api_key = $api_key;
            $this->api_service = new Flizpay_API_Service($api_key);

            if ($api_key === '') {
                $this->update_option('flizpay_webhook_alive', 'no');
                $this->init_gateway_info();
                return $saved;
            }

            $is_new_api_key = $api_key !== $previous_api_key;

            if ($is_new_api_key || $this->get_option('flizpay_webhook_alive') === 'no') {
                $this->update_option('flizpay_webhook_alive', 'no');
                usleep(500000); // Sleep for 0.5 seconds to wait for database update
                $connection = $this->api_service->establish_connection();

                if (is_wp_error($connection)) {
                    if ($is_new_api_key) {
                        $this->update_option('flizpay_api_key', '');
                    }
                } else {
                    $this->update_option('flizpay_webhook_key', $connection['webhook_key']);
                    $this->update_option('flizpay_webhook_url', $connection['webhook_url']);
                    $this->update_option('flizpay_api_key', $api_key);
                    $this->update_option('flizpay_cashback', $connection['cashback']);
                    $this->cashback = $connection['cashback'];
                }
            }

            $this->init_gateway_info();
            return $saved;
        }

        /**
         * Obtain the current cashback value of the merchant from the transient
         *
         * @return array{
         *     first_purchase_amount: float,
         *     standard_amount: float
         * }|null
         *
         * @since 1.0.0
         */
        public function get_cashback_data(): ?array
        {
            $cashback_data = $this->get_option('flizpay_cashback');

            if (
                !is_array($cashback_data) ||
                !isset(
                    $cashback_data['first_purchase_amount'],
                    $cashback_data['standard_amount']
                )
            ) {
                return null;
            }

            $first_purchase_amount = (float) $cashback_data['first_purchase_amount'];
            $standard_amount = (float) $cashback_data['standard_amount'];

            if ($first_purchase_amount <= 0 && $standard_amount <= 0) {
                return null;
            }

            return [
                'first_purchase_amount' => $first_purchase_amount,
                'standard_amount' => $standard_amount,
            ];
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
        public function register_webhook_endpoint(): void
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
        public function handle_webhook_request(): void
        {
            $this->webhook_helper->handle_webhook_request();
        }

        /**
         * Check if we are on the order-pay (Customer Payment Page) page.
         *
         * @return bool
         *
         * @since 1.0.0
         */
        private function is_order_pay_page(): bool
        {
            global $wp;

            return isset($wp->query_vars['order-pay']);
        }

        /**
         * Check whether the current customer's billing country is Germany.
         * Returns true when the country is 'DE' or when it cannot be determined
         * (empty/guest with no address yet), so new customers are not blocked
         * before they have entered an address.
         *
         * @return bool
         *
         * @since 2.5.0
         */
        private function is_german_customer(): bool
        {
            // Check serialised classic-checkout AJAX payload first — it reflects what
            // the customer just typed before WC()->customer is fully updated.
            if (!empty($_POST['post_data']) && is_string($_POST['post_data'])) {
                $post_data = array();
                parse_str(wp_unslash($_POST['post_data']), $post_data);
                $billing_country = sanitize_text_field($post_data['billing_country'] ?? '');
                if (!empty($billing_country)) {
                    return $billing_country === 'DE';
                }
            }

            if (!function_exists('WC') || !WC()->customer instanceof \WC_Customer) {
                return true; // Fail open in ambiguous contexts (REST, CLI, cron)
            }

            $billing_country = WC()->customer->get_billing_country();

            if (empty($billing_country)) {
                return true; // No address entered yet — don't hide FLIZpay prematurely
            }

            return $billing_country === 'DE';
        }

        /**
         * This plugin is only available outside of the admin order pay page.
         * It will also be marked as unavailable when the 2-way webhook connection was not established
         * and when the configuration haven't been completed at all.
         * When the merchant opts in via the "Restrict to Germany" setting, FLIZpay is additionally
         * hidden for customers whose billing country is not DE. By default the restriction is off,
         * since customers abroad may still bank with FLIZpay-supported providers (e.g. N26, Revolut).
         *
         * @return bool
         *
         * @since 1.0.0
         */
        public function is_available(): bool
        {
            if ($this->is_order_pay_page()) {
                return false; // Do not display in admin order management
            }

            if (!parent::is_available() || $this->get_option('flizpay_webhook_alive') !== 'yes') {
                return false;
            }

            // Country restriction is opt-in. Off by default so merchants can still
            // reach customers abroad who bank with FLIZpay-supported providers
            // (e.g. N26, Revolut). Shop owners who only serve Germany can enable
            // the "Restrict to Germany" setting to hide FLIZpay for non-DE addresses.
            if ($this->get_option('flizpay_restrict_to_germany') === 'yes') {
                return $this->is_german_customer();
            }

            return true;
        }

        /**
         * Plugin options, load the current settings
         *
         * @return void
         *
         * @since 1.0.0
         */
        public function init_form_fields(): void
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
        public function process_payment($order_id, $source = 'plugin'): array
        {
            try {
                $order = wc_get_order($order_id);
                $order->calculate_totals(true);
                $order->update_status($this->flizpay_order_status, 'FLIZpay Checkout initiated. Waiting for payment - ' . $source);
                $order->save();

                $attempt = (int) $order->get_meta('_flizpay_transaction_attempt');
                $idempotency_key = 'woocommerce-' . hash('sha256', $order->get_id() . ':' . $attempt);

                $transaction = $this->api_service->create_transaction($order, $source, $idempotency_key);

                if (
                    is_array($transaction) &&
                    !empty($transaction['reference']) &&
                    !empty($transaction['redirectUrl'])
                ) {
                    // Membership in this reference-keyed map authorizes later
                    // webhook and reconciliation settlements for the order.
                    // Idempotent creation replays return the same reference,
                    // so keyed writes naturally deduplicate.
                    $reference = (string) $transaction['reference'];
                    $transactions = $order->get_meta('_flizpay_transactions');
                    if (!is_array($transactions)) {
                        $transactions = array();
                    }

                    $transactions[$reference] = array(
                        'original_amount' => wc_format_decimal($order->get_total()),
                        'currency' => strtoupper((string) $order->get_currency()),
                        'attempt' => $attempt,
                    );

                    $order->update_meta_data('_flizpay_transactions', $transactions);
                    $order->save();

                    return array('result' => 'success', 'redirect' => $transaction['redirectUrl'], 'order_id' => $order_id);
                } else {
                    wc_add_notice('Error creating FLIZpay transaction. Please try again later.');
                    return array(
                        'result' => 'failure',
                        'redirect' => ''
                    );
                }
            } catch (\Exception $e) {
                Flizpay_Sentry::with_scope(static function ($scope) use ($e, $order, $order_id): void {
                    if ($scope && method_exists($scope, 'setExtras')) {
                        $scope->setExtras([
                            'function_name' => 'process_payment',
                            'message' => 'Exception during payment processing',
                            'order_id' => $order_id ?? null,
                            'shop_url' => home_url() ?? null,
                            'plugin_version' => self::$VERSION,
                        ]);
                    }

                    Flizpay_Sentry::capture_exception($e);
                });
                wc_add_notice('Error creating FLIZpay transaction. Please try again later.');
                return array(
                    'result' => 'failure',
                    'redirect' => ''
                );
            }
        }
    }
}
