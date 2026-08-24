<?php

declare(strict_types=1);


class Flizpay_Webhook_Helper
{
    private WC_Flizpay_Gateway $gateway;

    public function __construct(WC_Flizpay_Gateway $gateway)
    {
        $this->gateway = $gateway;
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
        if (
            !isset($data['metadata']) ||
            !is_array($data['metadata']) ||
            !isset($data['metadata']['orderId'], $data['status']) ||
            !is_numeric($data['metadata']['orderId']) ||
            !is_scalar($data['status'])
        ) {
            wp_send_json_error('Missing order_id or status', 400);
            return;
        }

        $order_id = (int) $data['metadata']['orderId'];
        $status = sanitize_text_field((string) $data['status']);
        $incoming_tx = isset($data['transactionId']) && is_scalar($data['transactionId'])
            ? sanitize_text_field((string) $data['transactionId'])
            : '';
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error('Order not found', 404);
            return;
        }

        if (!$this->is_tx_authorized_for_order($order, $incoming_tx)) {
            $order->add_order_note(sprintf(
                'FLIZpay webhook rejected: transactionId %s is not in this order\'s issued list',
                $incoming_tx !== '' ? $incoming_tx : '(missing)'
            ));
            wp_send_json_success(array('accepted' => false), 200);
            return;
        }

        if ($status === 'completed') {
            $this->complete_order($order, $data);
        } elseif ($status === 'failed') {
            $this->fail_order($order, $data);
        } elseif ($status === 'canceled') {
            $this->cancel_order($order, $data);
        }

        $order->save();
    }

    /**
     * A webhook is authorized only when its transactionId is one the plugin
     * recorded via process_payment for this specific order.
     */
    private function is_tx_authorized_for_order(\WC_Order $order, string $tx_id): bool
    {
        if ($tx_id === '') {
            return false;
        }
        $issued = $order->get_meta('_flizpay_issued_tx_ids');
        if (!is_array($issued) || empty($issued)) {
            return false;
        }
        return in_array($tx_id, $issued, true);
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
     * @param array<string, mixed> $data
     */
    private function fail_order(\WC_Order $order, array $data): void
    {
        $incoming_tx = isset($data['transactionId']) && is_scalar($data['transactionId'])
            ? sanitize_text_field((string) $data['transactionId'])
            : '';

        if ($incoming_tx !== '' && $order->get_meta('_flizpay_failed_tx') === $incoming_tx) {
            return;
        }

        $order->update_status('failed', __('Payment failed via FLIZpay', 'flizpay-for-woocommerce'));

        if ($incoming_tx !== '') {
            $order->update_meta_data('_flizpay_failed_tx', $incoming_tx);
            $this->advance_transaction_attempt($order);
            $order->add_order_note('FLIZ transaction ID: ' . $incoming_tx . ' — payment failed');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function cancel_order(\WC_Order $order, array $data): void
    {
        $incoming_tx = isset($data['transactionId']) && is_scalar($data['transactionId'])
            ? sanitize_text_field((string) $data['transactionId'])
            : '';

        if ($incoming_tx !== '' && $order->get_meta('_flizpay_canceled_tx') === $incoming_tx) {
            return;
        }

        $order->update_status('cancelled', __('Payment canceled via FLIZpay', 'flizpay-for-woocommerce'));

        if ($incoming_tx !== '') {
            $order->update_meta_data('_flizpay_canceled_tx', $incoming_tx);
            $this->advance_transaction_attempt($order);
            $order->add_order_note('FLIZ transaction ID: ' . $incoming_tx . ' — payment canceled by user or bank');
        }
    }

    private function advance_transaction_attempt(\WC_Order $order): void
    {
        $attempt = (int) $order->get_meta('_flizpay_transaction_attempt');
        $order->update_meta_data('_flizpay_transaction_attempt', $attempt + 1);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function complete_order(\WC_Order $order, array $data): void
    {
        $incoming_tx = isset($data['transactionId']) && is_scalar($data['transactionId'])
            ? sanitize_text_field((string) $data['transactionId'])
            : '';

        if ($incoming_tx !== '' && $order->get_meta('_flizpay_completed_tx') === $incoming_tx) {
            return;
        }

        // Explicitly set the payment method before completing payment
        $order->set_payment_method('flizpay');
        $order->set_payment_method_title('FLIZpay');

        $order->payment_complete($incoming_tx);

        if ($incoming_tx !== '') {
            $order->update_meta_data('_flizpay_completed_tx', $incoming_tx);
        }

        $original_amount = isset($data['originalAmount']) && is_numeric($data['originalAmount'])
            ? (float) $data['originalAmount']
            : 0.0;
        $amount = isset($data['amount']) && is_numeric($data['amount']) ? (float) $data['amount'] : 0.0;
        $currency = isset($data['currency']) && is_scalar($data['currency'])
            ? sanitize_text_field((string) $data['currency'])
            : '';
        $fliz_discount = $original_amount - $amount;
        $cashback_value = $original_amount > 0 ? ($fliz_discount * 100) / $original_amount : 0.0;

        if ($fliz_discount > 0) {
            $this->apply_cashback_discount($order, $cashback_value, $fliz_discount, $amount, $currency);
        }

        if ($incoming_tx !== '') {
            $order->add_order_note('FLIZ transaction ID: ' . $incoming_tx);
        }

        $this->send_order_emails($order->get_id());
    }

    private function apply_cashback_discount(
        \WC_Order $order,
        float $cashback_value,
        float $fliz_discount,
        float $amount,
        string $currency
    ): void
    {
        $line_items = $order->get_items('line_item');
        $shipping_items = $order->get_items('shipping');

        foreach ($line_items as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $item_subtotal = (float) $item->get_total();
            $discount_amount_fliz = ($item_subtotal * $cashback_value) / 100;
            $new_total = round($item_subtotal - $discount_amount_fliz, 2, PHP_ROUND_HALF_DOWN);
            $item->set_total($new_total);
            $item->save();
        }

        foreach ($shipping_items as $shipping) {
            if (!$shipping instanceof \WC_Order_Item_Shipping) {
                continue;
            }

            $shipping_total = (float) $shipping->get_total();
            $discount_amount_fliz = ($shipping_total * $cashback_value) / 100;
            $new_shipping_total = round($shipping_total - $discount_amount_fliz, 2, PHP_ROUND_HALF_DOWN);
            $shipping->set_total($new_shipping_total);
            $shipping->save();
        }

        $order->calculate_taxes();
        $order->calculate_totals(true);
        $order->set_total($amount);
        $order->add_order_note('FLIZ Discount Applied: ' . $currency . sanitize_text_field($fliz_discount));
        WC()->cart->empty_cart();
    }

    private function send_order_emails(int $order_id): void
    {
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();

        $completed_order = $emails['WC_Email_Customer_Completed_Order'] ?? null;
        if ($completed_order instanceof WC_Email_Customer_Completed_Order) {
            $completed_order->trigger($order_id);
        }

        $invoice = $emails['WC_Email_Customer_Invoice'] ?? null;
        if ($invoice instanceof WC_Email_Customer_Invoice) {
            $invoice->trigger($order_id);
        }

        $new_order = $emails['WC_Email_New_Order'] ?? null;
        if ($new_order instanceof \WC_Email_New_Order) {
            $new_order->trigger($order_id);
        }
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
