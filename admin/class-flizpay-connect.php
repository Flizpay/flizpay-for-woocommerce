<?php

/**
 * One-click connection to FLIZpay.
 *
 * This shop generates the connect code, so only someone who can reach these settings
 * can start a connection. The merchant then confirms it in the FLIZpay dashboard,
 * where the account is authenticated, and the shop exchanges its own code for that
 * account's API key. A code minted elsewhere cannot be redeemed here.
 *
 * @package    Flizpay
 * @subpackage Flizpay/admin
 */

class Flizpay_Connect
{
    private const SETTINGS_OPTION = 'woocommerce_flizpay_settings';
    private const CHALLENGE_OPTION = 'flizpay_connect_challenge';

    /** Matches the backend pairing window. */
    private const CHALLENGE_LIFETIME = 900;

    private const CODE_LENGTH = 12;

    /** Crockford-style alphabet: no 0/O/1/I, so the code survives being read aloud. */
    private const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /** Languages the FLIZpay dashboard serves, as its URL prefixes. */
    private const DASHBOARD_LANGUAGES = array('de', 'en');

    public function render_connect_panel()
    {
        if (!$this->is_flizpay_settings_screen() || !current_user_can('manage_woocommerce')) {
            return;
        }

        $challenge = $this->read_challenge();

        if ($challenge && !$this->has_expired($challenge)) {
            $this->render_pending_panel($challenge);
            return;
        }

        // The settings below already show the API key and connection state of a connected
        // shop; offering "Connect" again here would read as if nothing had happened.
        if ($this->is_connected()) {
            return;
        }

        $this->render_start_panel($challenge !== null);
    }

    private function is_connected()
    {
        $settings = get_option(self::SETTINGS_OPTION, array());
        if (!is_array($settings)) {
            return false;
        }

        return !empty($settings['flizpay_api_key']) && !empty($settings['flizpay_webhook_url']);
    }

    /**
     * Starts a connection: mints the code, remembers it, and hands the merchant to the
     * dashboard to confirm which FLIZpay account the shop should belong to.
     */
    public function handle_start_connect()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You are not allowed to connect FLIZpay.', 'flizpay-for-woocommerce'), 403);
        }
        check_admin_referer('flizpay_start_connect');

        $code = $this->generate_code();
        $shop_url = $this->get_shop_base_url();
        $language = $this->read_dashboard_language($_POST);

        update_option(self::CHALLENGE_OPTION, array(
            'code' => $code,
            'shop_url' => $shop_url,
            'language' => $language,
            'expires_at' => time() + self::CHALLENGE_LIFETIME,
        ), false);

        // wp_safe_redirect() would refuse an external host; the dashboard is a known FLIZpay host.
        wp_redirect($this->build_confirm_url($code, $shop_url, $language));
        exit;
    }

    /**
     * Retries the exchange on demand, for a merchant who confirmed in the dashboard but
     * did not land back on this screen.
     */
    public function handle_finish_connect()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You are not allowed to connect FLIZpay.', 'flizpay-for-woocommerce'), 403);
        }
        check_admin_referer('flizpay_finish_connect');

        $challenge = $this->read_challenge();
        if (!$challenge || $this->has_expired($challenge)) {
            delete_option(self::CHALLENGE_OPTION);
            $this->set_notice(__('This connection attempt expired. Start it again.', 'flizpay-for-woocommerce'));
        } else {
            $this->claim($challenge, true);
        }

        wp_safe_redirect($this->get_settings_screen_url());
        exit;
    }

    /**
     * Exchanges a pending code for the API key once the merchant has confirmed. Runs
     * where the merchant can see the outcome: this settings screen, or the return trip
     * from the dashboard.
     */
    public function maybe_claim()
    {
        if (!current_user_can('manage_woocommerce') || !$this->is_flizpay_settings_request()) {
            return;
        }

        $challenge = $this->read_challenge();
        if (!$challenge) {
            return;
        }

        if ($this->has_expired($challenge)) {
            delete_option(self::CHALLENGE_OPTION);
            return;
        }

        if (!$this->claim($challenge, false)) {
            return;
        }

        // WooCommerce already cached the gateway settings before admin_init, so this
        // request would still render an empty API key. Reload so the merchant sees the
        // stored connection instead of having to refresh.
        wp_safe_redirect($this->get_settings_screen_url());
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

    private function render_start_panel($was_expired)
    {
        ?>
        <div class="notice notice-info">
            <?php if ($was_expired) : ?>
                <p><?php echo esc_html__('This connection attempt expired. Start it again.', 'flizpay-for-woocommerce'); ?></p>
            <?php endif; ?>
            <p>
                <?php echo esc_html__('Connect this shop to your FLIZpay account:', 'flizpay-for-woocommerce'); ?>
                <strong><?php echo esc_html($this->get_shop_base_url()); ?></strong>
            </p>
            <p><?php echo esc_html__('You will confirm in your FLIZpay dashboard which account this shop belongs to.', 'flizpay-for-woocommerce'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="flizpay_start_connect">
                <input type="hidden" name="flizpay_lang" value="<?php echo esc_attr($this->read_dashboard_language($_GET)); ?>">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('flizpay_start_connect')); ?>">
                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Connect with FLIZpay', 'flizpay-for-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    private function render_pending_panel(array $challenge)
    {
        ?>
        <div class="notice notice-info">
            <p><?php echo esc_html__('Confirm your shop’s connection in your FLIZpay account.', 'flizpay-for-woocommerce'); ?></p>
            <p><?php echo esc_html__('If you’re not redirected automatically, open the FLIZpay setup and paste the connection code below.', 'flizpay-for-woocommerce'); ?></p>
            <p>
                <input type="text" id="flizpay-connect-code" readonly
                    value="<?php echo esc_attr($challenge['code']); ?>"
                    style="font-family: monospace; letter-spacing: 1px; width: 14em;"
                    onclick="this.select();">
                <button type="button" class="button" id="flizpay-copy-connect-code">
                    <?php echo esc_html__('Copy code', 'flizpay-for-woocommerce'); ?>
                </button>
                <a class="button" href="<?php echo esc_url($this->build_confirm_url($challenge['code'], $challenge['shop_url'], $this->challenge_language($challenge))); ?>">
                    <?php echo esc_html__('Open FLIZpay setup', 'flizpay-for-woocommerce'); ?>
                </a>
            </p>
            <hr>
            <p><?php echo esc_html__('Once you’ve entered the code and confirmed the connection in your FLIZ account, return here and click “Complete connection”.', 'flizpay-for-woocommerce'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="flizpay_finish_connect">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('flizpay_finish_connect')); ?>">
                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Complete connection', 'flizpay-for-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
        <script>
            (() => {
                const field = document.getElementById('flizpay-connect-code');
                const button = document.getElementById('flizpay-copy-connect-code');
                if (!field || !button) return;
                button.addEventListener('click', () => {
                    field.select();
                    navigator.clipboard?.writeText(field.value);
                });
            })();
        </script>
        <?php
    }

    /**
     * @return bool True once the code was redeemed, so the caller can reload the screen.
     *              A code the merchant has not confirmed yet leaves the challenge in place.
     */
    private function claim(array $challenge, $report_pending)
    {
        $api_key = Flizpay_API_Service::exchange_pairing($challenge['code'], $challenge['shop_url']);

        if (is_wp_error($api_key)) {
            // Not confirmed in the dashboard yet, which is the normal state while waiting.
            if ($api_key->get_error_code() === 'flizpay_connect_unconfirmed') {
                if ($report_pending) {
                    $this->set_notice($api_key->get_error_message());
                }
                return false;
            }
            $this->set_notice($api_key->get_error_message());
            return false;
        }

        delete_option(self::CHALLENGE_OPTION);

        $connection = $this->store_connection($api_key);
        $this->set_notice(is_wp_error($connection) ? $connection->get_error_message() : 'connected');

        return true;
    }

    private function store_connection($api_key)
    {
        $settings = get_option(self::SETTINGS_OPTION, array());
        $settings = is_array($settings) ? $settings : array();
        $settings['flizpay_api_key'] = $api_key;
        $settings['flizpay_webhook_alive'] = 'no';
        $settings['flizpay_public_id'] = '';
        delete_transient('flizpay_public_id_retry');
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
        update_option(self::SETTINGS_OPTION, $settings);

        return true;
    }

    private function generate_code()
    {
        $alphabet_size = strlen(self::CODE_ALPHABET);
        $code = '';
        for ($index = 0; $index < self::CODE_LENGTH; $index++) {
            $code .= self::CODE_ALPHABET[random_int(0, $alphabet_size - 1)];
        }
        return $code;
    }

    private function build_confirm_url($code, $shop_url, $language)
    {
        // FLIZPAY_DASHBOARD_BASE_URL (wp-config.php) points the plugin at a staging or local dashboard.
        $dashboard_base_url = defined('FLIZPAY_DASHBOARD_BASE_URL')
            ? untrailingslashit(FLIZPAY_DASHBOARD_BASE_URL)
            : 'https://app.flizpay.de';

        // add_query_arg() does not encode values, and the shop URL carries a scheme and slashes.
        $query = array(
            'platform=' . rawurlencode('WooCommerce'),
            'shop=' . rawurlencode($shop_url),
            'code=' . rawurlencode($code),
        );

        return $dashboard_base_url . '/' . $language . '/installation?' . implode('&', $query);
    }

    /**
     * The dashboard reads its language from the path, so returning the merchant to an
     * unprefixed URL would switch them to German. The dashboard hands its language over
     * in `flizpay_lang` when it links here; otherwise fall back to the language of the
     * admin who started the connection.
     *
     * @param array<string, mixed> $source
     */
    private function read_dashboard_language(array $source)
    {
        $requested = isset($source['flizpay_lang'])
            ? strtolower(sanitize_text_field(wp_unslash((string) $source['flizpay_lang'])))
            : '';

        if (in_array($requested, self::DASHBOARD_LANGUAGES, true)) {
            return $requested;
        }

        $locale = function_exists('get_user_locale') ? get_user_locale() : get_locale();

        return stripos((string) $locale, 'de') === 0 ? 'de' : 'en';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read_challenge()
    {
        $challenge = get_option(self::CHALLENGE_OPTION);
        if (!is_array($challenge) || empty($challenge['code']) || empty($challenge['shop_url'])) {
            return null;
        }
        return $challenge;
    }

    private function has_expired(array $challenge)
    {
        return (int) ($challenge['expires_at'] ?? 0) < time();
    }

    private function challenge_language(array $challenge)
    {
        $language = (string) ($challenge['language'] ?? '');

        return in_array($language, self::DASHBOARD_LANGUAGES, true) ? $language : $this->read_dashboard_language($_GET);
    }

    private function set_notice($message)
    {
        set_transient('flizpay_connect_notice_' . get_current_user_id(), $message, 60);
    }

    private function get_settings_screen_url()
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=flizpay');
    }

    /**
     * True when the current request targets the FLIZpay gateway settings screen. Works
     * before `get_current_screen()` exists, so `admin_init` can use it too.
     */
    private function is_flizpay_settings_request()
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';

        return $page === 'wc-settings' && $tab === 'checkout' && $section === 'flizpay';
    }

    private function is_flizpay_settings_screen()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'woocommerce_page_wc-settings') {
            return false;
        }

        return $this->is_flizpay_settings_request();
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
