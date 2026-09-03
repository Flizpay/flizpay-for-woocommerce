<?php

/**
 * One-click connection to FLIZpay.
 *
 * The merchant dashboard mints a one-time token and links the merchant to the FLIZpay
 * settings screen with the token in the URL fragment. This class names the FLIZpay
 * account behind that token, renders a confirm button, exchanges the token for an API
 * key, and then runs the same connection setup the manual API-key save performs.
 *
 * The token identifies the FLIZpay account that minted the link, not this shop, and
 * anyone can mint one for a shop URL they do not own. Naming the account is what lets
 * an administrator refuse a link that would connect this shop to a stranger.
 *
 * @package    Flizpay
 * @subpackage Flizpay/admin
 */
class Flizpay_Connect
{
    private const SETTINGS_OPTION = 'woocommerce_flizpay_settings';

    public function render_connect_form()
    {
        if (!$this->is_flizpay_settings_screen() || !current_user_can('manage_woocommerce')) {
            return;
        }
        ?>
        <div id="flizpay-connect-error" class="notice notice-error" style="display: none;">
            <p>
                <?php echo esc_html__('FLIZpay could not confirm which account this connection link belongs to. Reload the page to try again, or connect with an API key instead.', 'flizpay-for-woocommerce'); ?>
            </p>
        </div>
        <div id="flizpay-connect" class="notice notice-info" style="display: none;"
            data-preview-nonce="<?php echo esc_attr(wp_create_nonce('flizpay_connect_preview')); ?>">
            <p>
                <?php
                printf(
                    wp_kses(
                        /* translators: %s: base URL of this shop. */
                        __('This link connects <strong>%s</strong> to the FLIZpay account:', 'flizpay-for-woocommerce'),
                        array('strong' => array())
                    ),
                    esc_html($this->get_shop_base_url())
                );
                ?>
            </p>
            <p><strong id="flizpay-connect-account"></strong></p>
            <p>
                <?php echo esc_html__('Only continue if you recognise that account. If it is not yours, do not connect — your payments would be paid out to its owner.', 'flizpay-for-woocommerce'); ?>
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
                const failure = document.getElementById('flizpay-connect-error');

                const body = new URLSearchParams();
                body.set('action', 'flizpay_connect_preview');
                body.set('nonce', panel.dataset.previewNonce);
                body.set('flizpay_connect_token', token);

                fetch(ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body
                })
                    .then((response) => response.json())
                    .then((payload) => {
                        if (!payload || !payload.success || !payload.data || !payload.data.account) {
                            throw new Error('The connect token could not be previewed.');
                        }
                        document.getElementById('flizpay-connect-account').textContent = payload.data.account;
                        panel.querySelector('form').elements.flizpay_connect_token.value = token;
                        // Submitting is left to the administrator on purpose: auto-submitting would let
                        // any link carrying a token connect the shop to someone else's FLIZpay account.
                        panel.style.display = '';
                    })
                    .catch(() => {
                        // Fail closed: an unnamed account tells the merchant nothing about whose link this is.
                        failure.style.display = '';
                    });
            })();
        </script>
        <?php
    }

    /**
     * Name the FLIZpay account a connect token belongs to, so the administrator can
     * refuse a link that would hand this shop to someone else.
     */
    public function handle_preview_request()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('You are not allowed to connect FLIZpay.', 'flizpay-for-woocommerce'), 403);
        }
        check_ajax_referer('flizpay_connect_preview', 'nonce');

        $token = isset($_POST['flizpay_connect_token'])
            ? sanitize_text_field(wp_unslash($_POST['flizpay_connect_token']))
            : '';
        if (strlen($token) < 32) {
            wp_send_json_error(__('The connection link is invalid or expired.', 'flizpay-for-woocommerce'), 400);
        }

        $account = Flizpay_API_Service::preview_pairing($token, $this->get_shop_base_url());
        if (is_wp_error($account)) {
            wp_send_json_error($account->get_error_message(), 400);
        }

        wp_send_json_success(array('account' => $account));
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

        $api_key = Flizpay_API_Service::exchange_pairing($token, $this->get_shop_base_url());
        if (is_wp_error($api_key)) {
            return $api_key;
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

        $connection = (new Flizpay_API_Service($api_key))->establish_connection();
        if (is_wp_error($connection)) {
            return new WP_Error('flizpay_connect_failed', __('FLIZpay could not configure the webhook.', 'flizpay-for-woocommerce'));
        }

        $settings['flizpay_webhook_url'] = $connection['webhook_url'];
        $settings['flizpay_webhook_key'] = $connection['webhook_key'];
        $settings['flizpay_cashback'] = $connection['cashback'];
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
