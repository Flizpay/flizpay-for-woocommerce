<?php

/**
 * One-click connection to FLIZpay.
 *
 * The merchant dashboard mints a one-time token and links the merchant to the FLIZpay
 * settings screen with the token in the URL fragment. This class renders a confirm
 * button there, exchanges the token for an API key, and then runs the same connection
 * setup the manual API-key save performs.
 */
class Flizpay_Connect
{
    private const SETTINGS_OPTION = 'woocommerce_flizpay_settings';
    private const EXCHANGE_TIMEOUT = 20;

    public function render_connect_form()
    {
        if (!$this->is_flizpay_settings_screen() || !current_user_can('manage_woocommerce')) {
            return;
        }
        ?>
        <div id="flizpay-connect" class="notice notice-info" style="display: none;">
            <p>
                <?php echo esc_html__('FLIZpay would like to connect this shop:', 'flizpay-for-woocommerce'); ?>
                <strong><?php echo esc_html($this->get_shop_base_url()); ?></strong>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="flizpay_connect">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('flizpay_connect')); ?>">
                <input type="hidden" name="flizpay_connect_token" value="">
                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Connect this shop to FLIZpay', 'flizpay-for-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
        <script>
            (() => {
                const fragment = new URLSearchParams(window.location.hash.slice(1));
                const token = fragment.get('flizpay_connect_token');
                if (!token) return;

                window.history.replaceState(null, document.title, window.location.pathname + window.location.search);
                const panel = document.getElementById('flizpay-connect');
                panel.querySelector('form').elements.flizpay_connect_token.value = token;
                // Submitting is left to the administrator on purpose: auto-submitting would let
                // any link carrying a token connect the shop to someone else's FLIZpay account.
                panel.style.display = '';
            })();
        </script>
        <?php
    }

    public function handle_admin_connect()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You are not allowed to connect FLIZpay.', 'flizpay-for-woocommerce'), 403);
        }
        check_admin_referer('flizpay_connect');

        $token = isset($_POST['flizpay_connect_token'])
            ? sanitize_text_field(wp_unslash($_POST['flizpay_connect_token']))
            : '';
        $result = $this->connect($token);
        set_transient(
            'flizpay_connect_notice_' . get_current_user_id(),
            is_wp_error($result) ? $result->get_error_message() : 'connected',
            60
        );

        wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=checkout&section=flizpay'));
        exit;
    }

    public function render_admin_notice()
    {
        $key = 'flizpay_connect_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (!$notice) {
            return;
        }
        delete_transient($key);
        $success = $notice === 'connected';
        $message = $success
            ? __('FLIZpay was connected successfully.', 'flizpay-for-woocommerce')
            : sprintf(__('FLIZpay could not be connected: %s', 'flizpay-for-woocommerce'), $notice);
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            $success ? 'success' : 'error',
            esc_html($message)
        );
    }

    private function connect($token)
    {
        if (strlen($token) < 32) {
            return new WP_Error('flizpay_connect_invalid', __('The connection link is invalid or expired.', 'flizpay-for-woocommerce'));
        }

        $response = wp_remote_post($this->get_api_base_url() . '/business/woocommerce/pairings/exchange', array(
            'timeout' => self::EXCHANGE_TIMEOUT,
            'redirection' => 0,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array(
                'shopUrl' => $this->get_shop_base_url(),
                'token' => $token,
            )),
            'data_format' => 'body',
        ));
        if (is_wp_error($response)) {
            return new WP_Error('flizpay_connect_unreachable', __('FLIZpay could not be reached.', 'flizpay-for-woocommerce'));
        }

        $status = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        $api_key = is_array($decoded) && isset($decoded['data']['apiKey']) ? (string) $decoded['data']['apiKey'] : '';
        if ($status < 200 || $status >= 300 || $api_key === '') {
            $message = is_array($decoded) && !empty($decoded['message'])
                ? sanitize_text_field($decoded['message'])
                : __('FLIZpay rejected the connection.', 'flizpay-for-woocommerce');
            return new WP_Error('flizpay_connect_failed', $message);
        }

        $settings = get_option(self::SETTINGS_OPTION, array());
        $settings = is_array($settings) ? $settings : array();
        $settings['flizpay_api_key'] = $api_key;
        $settings['flizpay_webhook_alive'] = 'no';
        if (!isset($settings['enabled'])) {
            // WooCommerce defaults the field to 'yes', so leaving the key absent would
            // switch the gateway on. The merchant enables it explicitly.
            $settings['enabled'] = 'no';
        }
        update_option(self::SETTINGS_OPTION, $settings);

        // Same setup the manual API-key save runs: register the webhook URL, obtain the
        // webhook key (FLIZpay then sends a test webhook that flips flizpay_webhook_alive),
        // and cache the cashback configuration.
        try {
            $api_service = new Flizpay_API_Service($api_key);
            $webhook_url = $api_service->generate_webhook_url();
            $webhook_key = $api_service->get_webhook_key();
            $cashback_data = $api_service->fetch_cashback_data();
        } catch (\Exception $e) {
            Flizpay_Sentry::capture_exception($e);
            return new WP_Error('flizpay_connect_failed', __('FLIZpay could not configure the webhook.', 'flizpay-for-woocommerce'));
        }
        if (!$webhook_url || !$webhook_key) {
            return new WP_Error('flizpay_connect_failed', __('FLIZpay could not configure the webhook.', 'flizpay-for-woocommerce'));
        }

        $settings['flizpay_webhook_url'] = $webhook_url;
        $settings['flizpay_webhook_key'] = $webhook_key;
        $settings['flizpay_cashback'] = $cashback_data;
        update_option(self::SETTINGS_OPTION, $settings);

        return true;
    }

    /**
     * True on the FLIZpay payment gateway settings screen, which is where the merchant
     * dashboard's connection link points.
     */
    private function is_flizpay_settings_screen()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'woocommerce_page_wc-settings') {
            return false;
        }

        // Screen detection only; the connection itself is nonce- and capability-checked
        // in handle_admin_connect().
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';

        return $tab === 'checkout' && $section === 'flizpay';
    }

    private function get_api_base_url()
    {
        $base_url = defined('FLIZPAY_API_BASE_URL') ? FLIZPAY_API_BASE_URL : 'https://api.flizpay.de';
        return untrailingslashit($base_url);
    }

    private function get_shop_base_url()
    {
        $parts = wp_parse_url(home_url('/'));
        $base_url = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $base_url .= ':' . $parts['port'];
        }
        if (!empty($parts['path'])) {
            $base_url .= untrailingslashit($parts['path']);
        }
        return $base_url;
    }
}
