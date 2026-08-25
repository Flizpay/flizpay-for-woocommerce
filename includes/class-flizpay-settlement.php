<?php

declare(strict_types=1);

/**
 * Validates provider data and applies idempotent FLIZpay order transitions.
 *
 * @phpstan-type SettlementData array{
 *     order_id: int,
 *     transaction_id: string,
 *     status: string,
 *     amount: string,
 *     original_amount: string,
 *     currency: string
 * }
 * @phpstan-type SettlementResult array{
 *     success: bool,
 *     result: string,
 *     message: string
 * }
 * @phpstan-type ReferenceData array{
 *     reference: string,
 *     original_amount: string,
 *     currency: string,
 *     attempt: int
 * }
 */
class Flizpay_Settlement
{
    /**
     * Validate provider data and apply an idempotent order transition.
     *
     * Webhook payloads may contain the order ID under metadata, while
     * reconciliation responses contain it at the top level.
     *
     * @param array<string, mixed> $data
     * @param string $source Settlement source: webhook or reconciliation.
     * @param string|null $requested_reference Reference used for a reconciliation request.
     * @return SettlementResult Applied, rejected, duplicate, or no-change outcome.
     */
    public function settle(array $data, string $source = 'webhook', ?string $requested_reference = null): array
    {
        $normalized = $this->normalize($data);
        if ($normalized === null) {
            return $this->reject('invalid_payload', 'Missing or invalid settlement data.');
        }

        $order = wc_get_order($normalized['order_id']);
        if (!$order instanceof \WC_Order) {
            return $this->reject('order_not_found', 'Order not found.', $normalized);
        }

        $validation = $this->validate($order, $normalized, $source, $requested_reference);
        if ($validation !== null) {
            return $validation;
        }

        if (in_array($normalized['status'], array('pending', 'processing'), true)) {
            return $this->result(true, 'no_change', 'Provider transaction is not terminal.');
        }

        $state_result = $this->check_state($order, $normalized);
        if ($state_result !== null) {
            return $state_result;
        }

        // Force a fresh read before mutation so concurrent requests get another state check.
        clean_post_cache($order->get_id());
        wp_cache_delete($order->get_id(), 'orders');
        $order = wc_get_order($order->get_id());
        if (!$order instanceof \WC_Order) {
            return $this->reject('order_not_found', 'Order not found.', $normalized);
        }

        $validation = $this->validate($order, $normalized, $source, $requested_reference);
        if ($validation !== null) {
            return $validation;
        }

        $state_result = $this->check_state($order, $normalized);
        if ($state_result !== null) {
            return $state_result;
        }

        if ($normalized['status'] === 'completed') {
            return $this->complete_order($order, $normalized);
        }

        if ($normalized['status'] === 'failed') {
            return $this->fail_order($order, $normalized);
        }

        return $this->cancel_order($order, $normalized);
    }

    /**
     * Validate and normalize webhook or reconciliation provider fields.
     *
     * @param array<string, mixed> $data
     * @return SettlementData|null Normalized data, or null for an invalid payload.
     */
    private function normalize(array $data): ?array
    {
        $order_id = $data['orderId'] ?? $data['metadata']['orderId'] ?? null;
        $transaction_id = $data['transactionId'] ?? null;
        $status = $data['status'] ?? null;
        $amount = $data['amount'] ?? null;
        $original_amount = $data['originalAmount'] ?? null;
        $currency = $data['currency'] ?? null;

        if (
            !is_numeric($order_id) ||
            !is_scalar($transaction_id) ||
            !is_scalar($status) ||
            !is_numeric($amount) ||
            !is_numeric($original_amount) ||
            !is_scalar($currency)
        ) {
            return null;
        }

        $normalized = array(
            'order_id' => (int) $order_id,
            'transaction_id' => sanitize_text_field((string) $transaction_id),
            'status' => strtolower(sanitize_text_field((string) $status)),
            'amount' => wc_format_decimal($amount),
            'original_amount' => wc_format_decimal($original_amount),
            'currency' => strtoupper(sanitize_text_field((string) $currency)),
        );

        if (
            $normalized['order_id'] <= 0 ||
            $normalized['transaction_id'] === '' ||
            $normalized['currency'] === '' ||
            $normalized['amount'] === '' ||
            $normalized['original_amount'] === ''
        ) {
            return null;
        }

        return $normalized;
    }

    /**
     * Validate normalized provider data against the order and transaction snapshot.
     *
     * Legacy webhook transactions without a snapshot use the order's current amount
     * and currency. Reconciliation always requires a matching stored reference.
     *
     * @param SettlementData $data
     * @param string $source Settlement source: webhook or reconciliation.
     * @param string|null $requested_reference Reference used for a reconciliation request.
     * @return array{success: false, result: string, message: string}|null Rejection details, or null when valid.
     */
    private function validate(\WC_Order $order, array $data, string $source, ?string $requested_reference): ?array
    {
        if ($order->get_payment_method() !== 'flizpay') {
            return $this->reject('payment_method_mismatch', 'Order does not use FLIZpay.', $data);
        }

        if ($order->get_id() !== $data['order_id']) {
            return $this->reject('order_mismatch', 'Provider order ID does not match.', $data);
        }

        $issued = $order->get_meta('_flizpay_issued_tx_ids');
        if (!is_array($issued) || !in_array($data['transaction_id'], $issued, true)) {
            return $this->reject('transaction_mismatch', 'Transaction is not authorized for this order.', $data);
        }

        if (!in_array($data['status'], array('completed', 'failed', 'canceled', 'pending', 'processing'), true)) {
            return $this->reject('unknown_status', 'Provider status is not recognized.', $data);
        }

        $reference_data = $this->get_reference_data($order, $data['transaction_id']);
        if ($source === 'reconciliation') {
            if ($reference_data === null || $requested_reference === null || $requested_reference === '') {
                return $this->reject('missing_reference', 'Transaction reference is unavailable.', $data);
            }

            if (!hash_equals($reference_data['reference'], $requested_reference)) {
                return $this->reject('reference_mismatch', 'Transaction reference does not match.', $data);
            }
        }

        $expected_amount = $reference_data['original_amount'] ?? wc_format_decimal($order->get_total());
        $expected_currency = $reference_data['currency'] ?? strtoupper((string) $order->get_currency());
        $precision = wc_get_price_decimals();

        if (wc_format_decimal($data['original_amount'], $precision) !== wc_format_decimal($expected_amount, $precision)) {
            return $this->reject('amount_mismatch', 'Provider original amount does not match.', $data);
        }

        if ($data['currency'] !== strtoupper((string) $expected_currency)) {
            return $this->reject('currency_mismatch', 'Provider currency does not match.', $data);
        }

        $amount = (float) wc_format_decimal($data['amount'], $precision);
        $original_amount = (float) wc_format_decimal($data['original_amount'], $precision);
        if ($amount <= 0 || $original_amount <= 0 || $amount > $original_amount) {
            return $this->reject('invalid_amount', 'Provider amount is outside the accepted range.', $data);
        }

        return null;
    }

    /**
     * Read the transaction-time reference, amount, currency, and attempt snapshot.
     *
     * @return ReferenceData|null Stored reference data, or null for legacy transactions.
     */
    private function get_reference_data(\WC_Order $order, string $transaction_id): ?array
    {
        $references = $order->get_meta('_flizpay_transaction_references');
        if (!is_array($references) || !isset($references[$transaction_id]) || !is_array($references[$transaction_id])) {
            return null;
        }

        $reference = $references[$transaction_id];
        if (!isset($reference['reference']) || !is_scalar($reference['reference'])) {
            return null;
        }

        return array(
            'reference' => sanitize_text_field((string) $reference['reference']),
            'original_amount' => isset($reference['original_amount']) && is_numeric($reference['original_amount'])
                ? wc_format_decimal($reference['original_amount'])
                : wc_format_decimal($order->get_total()),
            'currency' => isset($reference['currency']) && is_scalar($reference['currency'])
                ? strtoupper(sanitize_text_field((string) $reference['currency']))
                : strtoupper((string) $order->get_currency()),
            'attempt' => isset($reference['attempt']) ? (int) $reference['attempt'] : 0,
        );
    }

    /**
     * Check terminal markers, paid state, and stale-attempt rules before mutation.
     *
     * @param SettlementData $data
     * @return SettlementResult|null A no-op result, or null when mutation may proceed.
     */
    private function check_state(\WC_Order $order, array $data): ?array
    {
        $terminal_keys = array(
            'completed' => '_flizpay_completed_tx',
            'failed' => '_flizpay_failed_tx',
            'canceled' => '_flizpay_canceled_tx',
        );
        $terminal_key = $terminal_keys[$data['status']];
        if ($order->get_meta($terminal_key) === $data['transaction_id']) {
            return $this->noop('duplicate', 'Transaction was already processed.', $data);
        }

        if ($data['status'] === 'completed') {
            if ($order->is_paid() || $order->has_status(array('completed', 'processing'))) {
                return $this->noop('already_paid', 'Order is already paid.', $data);
            }

            return null;
        }

        if ($order->is_paid() || $order->has_status(array('completed', 'processing'))) {
            return $this->noop('already_paid', 'Paid orders cannot be failed or cancelled.', $data);
        }

        $reference_data = $this->get_reference_data($order, $data['transaction_id']);
        if ($reference_data !== null && $reference_data['attempt'] < (int) $order->get_meta('_flizpay_transaction_attempt')) {
            return $this->noop('older_attempt', 'Older attempts cannot terminalize this order.', $data);
        }

        return null;
    }

    /**
     * Apply a completed payment, optional cashback discount, notes, and emails.
     *
     * @param SettlementData $data
     * @return SettlementResult
     */
    private function complete_order(\WC_Order $order, array $data): array
    {
        $original_amount = (float) $data['original_amount'];
        $amount = (float) $data['amount'];
        $discount = $original_amount - $amount;

        if ($discount > 0) {
            $this->apply_cashback_discount($order, ($discount * 100) / $original_amount, $discount, $amount, $data['currency']);
        }

        $order->set_payment_method('flizpay');
        $order->set_payment_method_title('FLIZpay');
        $order->update_meta_data('_flizpay_completed_tx', $data['transaction_id']);
        $order->save();
        $order->payment_complete($data['transaction_id']);
        $order->add_order_note('FLIZ transaction ID: ' . $data['transaction_id']);
        $order->save();
        $this->send_order_emails($order->get_id());

        return $this->applied('completed', 'Order payment completed.', $data);
    }

    /**
     * Mark the transaction and order as failed, then advance the payment attempt.
     *
     * @param SettlementData $data
     * @return SettlementResult
     */
    private function fail_order(\WC_Order $order, array $data): array
    {
        $order->update_meta_data('_flizpay_failed_tx', $data['transaction_id']);
        $this->advance_transaction_attempt($order);
        $order->save();
        $order->update_status('failed', __('Payment failed via FLIZpay', 'flizpay-for-woocommerce'));
        $order->add_order_note('FLIZ transaction ID: ' . $data['transaction_id'] . ' - payment failed');
        $order->save();

        return $this->applied('failed', 'Order payment failed.', $data);
    }

    /**
     * Mark the transaction and order as cancelled, then advance the payment attempt.
     *
     * @param SettlementData $data
     * @return SettlementResult
     */
    private function cancel_order(\WC_Order $order, array $data): array
    {
        $order->update_meta_data('_flizpay_canceled_tx', $data['transaction_id']);
        $this->advance_transaction_attempt($order);
        $order->save();
        $order->update_status('cancelled', __('Payment canceled via FLIZpay', 'flizpay-for-woocommerce'));
        $order->add_order_note('FLIZ transaction ID: ' . $data['transaction_id'] . ' - payment canceled by user or bank');
        $order->save();

        return $this->applied('canceled', 'Order payment cancelled.', $data);
    }

    /**
     * Increment the attempt used to generate the next idempotency key.
     */
    private function advance_transaction_attempt(\WC_Order $order): void
    {
        $order->update_meta_data(
            '_flizpay_transaction_attempt',
            (int) $order->get_meta('_flizpay_transaction_attempt') + 1
        );
    }

    /**
     * Distribute the provider discount across products and shipping.
     *
     * Recalculates taxes and totals, records an order note, and empties the
     * frontend cart when one is available.
     */
    private function apply_cashback_discount(
        \WC_Order $order,
        float $cashback_value,
        float $discount,
        float $amount,
        string $currency
    ): void {
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $item_total = (float) $item->get_total();
            $item->set_total(round($item_total - (($item_total * $cashback_value) / 100), 2, PHP_ROUND_HALF_DOWN));
            $item->save();
        }

        foreach ($order->get_items('shipping') as $shipping) {
            if (!$shipping instanceof \WC_Order_Item_Shipping) {
                continue;
            }

            $shipping_total = (float) $shipping->get_total();
            $shipping->set_total(round($shipping_total - (($shipping_total * $cashback_value) / 100), 2, PHP_ROUND_HALF_DOWN));
            $shipping->save();
        }

        $order->calculate_taxes();
        $order->calculate_totals(true);
        $order->set_total($amount);
        $order->add_order_note('FLIZ Discount Applied: ' . $currency . wc_format_decimal($discount));

        if (WC()->cart) {
            WC()->cart->empty_cart();
        }
    }

    /**
     * Trigger the settlement emails historically sent by the webhook flow.
     */
    private function send_order_emails(int $order_id): void
    {
        $emails = WC()->mailer()->get_emails();

        $completed_order = $emails['WC_Email_Customer_Completed_Order'] ?? null;
        if ($completed_order instanceof \WC_Email_Customer_Completed_Order) {
            $completed_order->trigger($order_id);
        }

        $invoice = $emails['WC_Email_Customer_Invoice'] ?? null;
        if ($invoice instanceof \WC_Email_Customer_Invoice) {
            $invoice->trigger($order_id);
        }

        $new_order = $emails['WC_Email_New_Order'] ?? null;
        if ($new_order instanceof \WC_Email_New_Order) {
            $new_order->trigger($order_id);
        }
    }

    /**
     * Log and return a validation rejection.
     *
     * @param SettlementData|array<string, mixed> $data
     * @return array{success: false, result: string, message: string}
     */
    private function reject(string $result, string $message, array $data = array()): array
    {
        $this->log('warning', $message, $result, $data);
        return $this->result(false, $result, $message);
    }

    /**
     * Log and return an accepted no-op outcome.
     *
     * @param SettlementData $data
     * @return array{success: true, result: string, message: string}
     */
    private function noop(string $result, string $message, array $data): array
    {
        $this->log('info', $message, $result, $data);
        return $this->result(true, $result, $message);
    }

    /**
     * Log and return an applied terminal transition.
     *
     * @param SettlementData $data
     * @return array{success: true, result: string, message: string}
     */
    private function applied(string $result, string $message, array $data): array
    {
        $this->log('info', $message, $result, $data);
        return $this->result(true, $result, $message);
    }

    /**
     * Build the standard settlement result consumed by webhook and reconciliation callers.
     *
     * @return SettlementResult
     */
    private function result(bool $success, string $result, string $message): array
    {
        return array('success' => $success, 'result' => $result, 'message' => $message);
    }

    /**
     * Write a sanitized settlement event to the WooCommerce logger.
     *
     * @param SettlementData|array<string, mixed> $data
     */
    private function log(string $level, string $message, string $result, array $data): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->log($level, $message, array(
            'source' => 'flizpay-reconciliation',
            'order_id' => $data['order_id'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'provider_status' => $data['status'] ?? null,
            'result' => $result,
        ));
    }
}
