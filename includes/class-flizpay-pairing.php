<?php

/**
 * Handles one-click pairing without exposing WordPress administrator credentials.
 */
class Flizpay_Pairing
{
    private const REST_NAMESPACE = 'flizpay/v1';
    private const CHALLENGE_TRANSIENT_PREFIX = 'flizpay_pairing_challenge_';
    private const SETTINGS_OPTION = 'woocommerce_flizpay_settings';

    public function register_rest_routes()
    {
        register_rest_route(self::REST_NAMESPACE, '/pair', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_pair_request'),
            'permission_callback' => '__return_true',
        ));
        register_rest_route(self::REST_NAMESPACE, '/challenge', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_challenge_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public function maybe_pair_from_admin_link()
    {
        if (!is_admin() || !current_user_can('manage_woocommerce')) {
            return;
        }

        $pairing_token = isset($_GET['flizpay_pairing_token'])
            ? sanitize_text_field(wp_unslash($_GET['flizpay_pairing_token']))
            : '';
        $pairing_id = isset($_GET['flizpay_pairing'])
            ? sanitize_text_field(wp_unslash($_GET['flizpay_pairing']))
            : '';
        $api_base_url = isset($_GET['flizpay_api_url'])
            ? esc_url_raw(wp_unslash($_GET['flizpay_api_url']))
            : '';
        if ($pairing_id === '' || $pairing_token === '' || $api_base_url === '') {
            return;
        }

        $result = $this->pair($pairing_id, $pairing_token, $api_base_url);
        set_transient(
            'flizpay_pairing_notice_' . get_current_user_id(),
            is_wp_error($result) ? $result->get_error_message() : 'connected',
            60
        );

        wp_safe_redirect(remove_query_arg(array(
            'flizpay_pairing',
            'flizpay_pairing_token',
            'flizpay_api_url',
        )));
        exit;
    }

    public function render_admin_notice()
    {
        $key = 'flizpay_pairing_notice_' . get_current_user_id();
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

    public function handle_pair_request(WP_REST_Request $request)
    {
        $pairing_id = sanitize_text_field((string) $request->get_param('pairingId'));
        $pairing_token = sanitize_text_field((string) $request->get_param('pairingToken'));
        $api_base_url = esc_url_raw((string) $request->get_param('apiBaseUrl'));
        $enable_gateway = rest_sanitize_boolean($request->get_param('enableGateway'));
        $result = $this->pair($pairing_id, $pairing_token, $api_base_url, $enable_gateway);
        if (is_wp_error($result)) {
            return $result;
        }
        return new WP_REST_Response($result, 200);
    }

    public function handle_challenge_request(WP_REST_Request $request)
    {
        $pairing_id = sanitize_text_field((string) $request->get_param('pairingId'));
        $nonce = sanitize_text_field((string) $request->get_param('nonce'));
        $secret = get_transient($this->challenge_transient_key($pairing_id));
        if (!is_string($secret) || strlen($secret) < 32 || $pairing_id === '' || strlen($nonce) < 32) {
            return new WP_Error('flizpay_challenge_invalid', 'Pairing challenge is invalid or expired', array('status' => 403));
        }

        $payload = $pairing_id . '.' . $nonce . '.' . $this->get_shop_origin();
        return new WP_REST_Response(array('signature' => hash_hmac('sha256', $payload, $secret)), 200);
    }

    public static function disconnect_managed_connection($settings)
    {
        if (!is_array($settings) || empty($settings['flizpay_connection_id']) || empty($settings['flizpay_api_key'])) {
            return false;
        }
        $api_base_url = self::get_saved_api_base_url($settings);
        $response = wp_remote_request($api_base_url . '/business/woocommerce/connections/current', array(
            'method' => 'DELETE',
            'timeout' => 10,
            'redirection' => 0,
            'headers' => array('x-api-key' => $settings['flizpay_api_key']),
        ));
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) >= 200
            && wp_remote_retrieve_response_code($response) < 300;
    }

    private function pair($pairing_id, $pairing_token, $api_base_url, $enable_gateway = false)
    {
        if ($pairing_id === '' || strlen($pairing_token) < 32 || !$this->is_allowed_api_base_url($api_base_url)) {
            return new WP_Error('flizpay_pairing_input_invalid', 'Pairing request is invalid', array('status' => 400));
        }

        $api_base_url = untrailingslashit($api_base_url);
        $challenge_secret = wp_generate_password(64, false, false);
        $challenge_transient_key = $this->challenge_transient_key($pairing_id);
        set_transient($challenge_transient_key, $challenge_secret, 120);
        $previous_settings = get_option(self::SETTINGS_OPTION, array());

        $claim = $this->request_json(
            $api_base_url . '/business/woocommerce/pairings/claim',
            array(
                'pairingId' => $pairing_id,
                'pairingToken' => $pairing_token,
                'shopUrl' => $this->get_shop_origin(),
                'webhookUrl' => home_url('/flizpay-webhook/'),
                'pluginVersion' => FLIZPAY_VERSION,
                'challengeSecret' => $challenge_secret,
            )
        );
        if (is_wp_error($claim)) {
            delete_transient($challenge_transient_key);
            return $claim;
        }

        $credentials = isset($claim['data']) && is_array($claim['data']) ? $claim['data'] : array();
        if (empty($credentials['apiKey']) || empty($credentials['webhookKey']) || empty($credentials['pairingId'])) {
            delete_transient($challenge_transient_key);
            return new WP_Error('flizpay_pairing_response_invalid', 'FLIZpay returned invalid credentials');
        }

        $settings = is_array($previous_settings) ? $previous_settings : array();
        $settings['flizpay_api_key'] = $credentials['apiKey'];
        $settings['flizpay_webhook_key'] = $credentials['webhookKey'];
        $settings['flizpay_webhook_url'] = home_url('/flizpay-webhook/');
        $settings['flizpay_webhook_alive'] = 'no';
        $settings['flizpay_api_base_url'] = $api_base_url;
        $settings['flizpay_connection_id'] = sanitize_text_field((string) $credentials['connectionId']);
        $settings['flizpay_credential_revision'] = absint($credentials['credentialRevision']);
        update_option(self::SETTINGS_OPTION, $settings, false);
        delete_option('flizpay_rewrite_rules_flushed');
        flush_rewrite_rules(false);

        $finalize = $this->request_json(
            $api_base_url . '/business/woocommerce/pairings/' . rawurlencode($credentials['pairingId']) . '/finalize',
            array('pairingToken' => $pairing_token),
            array('x-api-key' => $credentials['apiKey'])
        );
        delete_transient($challenge_transient_key);
        if (is_wp_error($finalize)) {
            update_option(self::SETTINGS_OPTION, $previous_settings, false);
            return $finalize;
        }

        $settings['flizpay_webhook_alive'] = 'yes';
        if ($enable_gateway) {
            $settings['enabled'] = 'yes';
        }
        update_option(self::SETTINGS_OPTION, $settings, false);
        return array(
            'connected' => true,
            'connectionId' => $settings['flizpay_connection_id'],
            'credentialRevision' => $settings['flizpay_credential_revision'],
        );
    }

    private function challenge_transient_key($pairing_id)
    {
        return self::CHALLENGE_TRANSIENT_PREFIX . sanitize_key($pairing_id);
    }

    private function request_json($url, $body, $headers = array())
    {
        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'redirection' => 0,
            'headers' => array_merge(array('Content-Type' => 'application/json'), $headers),
            'body' => wp_json_encode($body),
            'data_format' => 'body',
        ));
        if (is_wp_error($response)) {
            return new WP_Error('flizpay_pairing_unreachable', 'FLIZpay could not be reached');
        }

        $status = wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $message = is_array($decoded) && !empty($decoded['message']) ? $decoded['message'] : 'FLIZpay pairing failed';
            return new WP_Error('flizpay_pairing_failed', sanitize_text_field($message), array('status' => $status));
        }
        return $decoded;
    }

    private function is_allowed_api_base_url($value)
    {
        $parts = wp_parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        if ($parts['scheme'] === 'https' && ($host === 'flizpay.de' || str_ends_with($host, '.flizpay.de'))) {
            return true;
        }
        return defined('WP_DEBUG') && WP_DEBUG && $parts['scheme'] === 'http'
            && in_array($host, array('localhost', '127.0.0.1', 'host.docker.internal'), true);
    }

    private function get_shop_origin()
    {
        $parts = wp_parse_url(home_url('/'));
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }

    private static function get_saved_api_base_url($settings)
    {
        $value = !empty($settings['flizpay_api_base_url']) ? $settings['flizpay_api_base_url'] : 'https://api.flizpay.de';
        return untrailingslashit($value);
    }
}
