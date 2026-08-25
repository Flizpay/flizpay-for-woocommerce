<?php

declare(strict_types=1);


class Flizpay_Webhook_Helper
{
    private WC_Flizpay_Gateway $gateway;
    private Flizpay_Settlement $settlement;

    public function __construct(WC_Flizpay_Gateway $gateway, Flizpay_Settlement $settlement)
    {
        $this->gateway = $gateway;
        $this->settlement = $settlement;
    }

    public function register_webhook_endpoint(): void
    {
        add_rewrite_tag('%flizpay-webhook%', '([^&]+)');
        add_rewrite_rule('^flizpay-webhook/?', 'index.php?flizpay-webhook=1&source=woocommerce', 'top');

        // Check if rewrite rules need to be flushed
        if ($this->should_flush_rewrite_rules()) {
            flush_rewrite_rules();
            update_option('flizpay_rewrite_rules_flushed', true);
        }
    }

    private function should_flush_rewrite_rules(): bool
    {
        // Always flush if rules haven't been flushed before
        if (!get_option('flizpay_rewrite_rules_flushed')) {
            return true;
        }

        // Check if our rewrite rule exists in WordPress rewrite rules
        global $wp_rewrite;
        $current_rules = $wp_rewrite->wp_rewrite_rules();

        return !is_array($current_rules) || !isset($current_rules['^flizpay-webhook/?']);
    }

    public function handle_webhook_request(): void
    {
        global $wp;

        if (isset($wp->query_vars['flizpay-webhook'])) {
            $data = $this->get_webhook_data();

            if ($data === null) {
                wp_send_json_error('Invalid JSON', 422);
                return;
            }

            $authenticated = $this->webhook_authenticate($data);

            if (! $authenticated) {
                wp_send_json_error('Authentication failed', 401);
                return;
            }


            if (isset($data['test'])) {
                $this->update_webhook_status(true);
                wp_send_json_success(array('alive' => true), 200);
            } elseif (isset($data['updateCashbackInfo'])) {
                if (
                    !isset($data['firstPurchaseAmount'], $data['amount']) ||
                    !is_numeric($data['firstPurchaseAmount']) ||
                    !is_numeric($data['amount'])
                ) {
                    wp_send_json_error('Missing cashback information', 400);
                    return;
                }

                $this->update_cashback_info($data);
                wp_send_json_success('Cashback information updated', 200);
            } else {
                $this->finish_order($data);
                wp_send_json_success('Order updated successfully', 200);
            }
        }

        return; // Do not process the request
    }

    /**
     * @param array<string, mixed> $data
     */
    public function finish_order(array $data): void
    {
        $result = $this->settlement->settle($data, 'webhook');
        if (!$result['success']) {
            wp_send_json_success(array('accepted' => false, 'reason' => $result['result']), 200);
            return;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function webhook_authenticate(array $data): bool
    {
        $key = $this->gateway->get_option('flizpay_webhook_key');

        if (!is_string($key) || strlen($key) < 32) {
            return false;
        }

        if (!isset($_SERVER['HTTP_X_FLIZ_SIGNATURE'])) {
            return false;
        }

        if (!is_scalar($_SERVER['HTTP_X_FLIZ_SIGNATURE'])) {
            return false;
        }

        $signature = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_X_FLIZ_SIGNATURE']));
        $signedData = hash_hmac('sha256', wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $key);
        return hash_equals($signature, $signedData);
    }

    private function update_webhook_status(bool $status): void
    {
        $this->gateway->update_option('flizpay_webhook_alive', $status ? 'yes' : 'no');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update_cashback_info(array $data): void
    {
        $first_purchase_amount = floatval($data['firstPurchaseAmount']);
        $standard_amount = floatval($data['amount']);
        $cashback = array(
            'first_purchase_amount' => $first_purchase_amount,
            'standard_amount' => $standard_amount
        );
        $this->gateway->cashback = $cashback;
        $this->gateway->update_option('flizpay_cashback', $cashback);
        $this->gateway->init_gateway_info();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function get_webhook_data(): ?array
    {
        // Step 1: Read the raw POST body input (JSON or other) from the request.
        $raw = file_get_contents('php://input');

        // Step 2: Fallback for WordPress behavior where some requests might use form-encoded POST.
        if (isset($_POST['data'])) {
            $raw = stripslashes((string) $_POST['data']);
        }

        if (!is_string($raw)) {
            return null;
        }

        // Step 3: Remove BOM (Byte Order Mark) if it exists at the start of the string.
        $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
        if ($raw === null) {
            return null;
        }

        // Step 4: Decode the JSON into an associative array.
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (\JsonException $e) {
            Flizpay_Sentry::with_scope(static function ($scope) use ($e): void {
                if ($scope && method_exists($scope, 'setExtras')) {
                    $scope->setExtras([
                        'function_name' => 'get_webhook_data',
                        'message' => 'Exception during extracting webhook data',
                    ]);
                }

                Flizpay_Sentry::capture_exception($e);
            });
            return null;
        }
    }
}
