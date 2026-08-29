<?php

declare(strict_types=1);

/**
 * Validates provider data and applies idempotent FLIZpay order transitions.
 *
 * @phpstan-type SettlementData array{
 *     order_id: int,
 *     reference: string,
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
 * @phpstan-type TransactionSnapshot array{
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
     * `reference` identifies the transaction. A payload carrying only the
     * deprecated `transactionId` remains valid so webhooks can still settle
     * legacy orders placed on plugin <= 2.5.3; at least one identifier is
     * required.
     *
     * @param array<string, mixed> $data
     * @return SettlementData|null Normalized data, or null for an invalid payload.
     */
    private function normalize(array $data): ?array
    {
        $order_id = $data['orderId'] ?? $data['metadata']['orderId'] ?? null;
        $reference = $data['reference'] ?? null;
        $status = $data['status'] ?? null;
        $amount = $data['amount'] ?? null;
        $original_amount = $data['originalAmount'] ?? null;
        $currency = $data['currency'] ?? null;

        if (
            !is_numeric($order_id) ||
            !is_scalar($status) ||
            !is_numeric($amount) ||
            !is_numeric($original_amount) ||
            !is_scalar($currency)
        ) {
            return null;
        }

        $normalized = array(
            'order_id' => (int) $order_id,
            'reference' => is_scalar($reference) ? sanitize_text_field((string) $reference) : '',
            // Legacy (<= 2.5.3): webhooks for legacy orders may carry only a transactionId.
            'transaction_id' => $this->legacy_transaction_id($data),
            'status' => strtolower(sanitize_text_field((string) $status)),
            'amount' => wc_format_decimal($amount),
            'original_amount' => wc_format_decimal($original_amount),
            'currency' => strtoupper(sanitize_text_field((string) $currency)),
        );

        if (
            $normalized['order_id'] <= 0 ||
            // Legacy (<= 2.5.3): becomes `$normalized['reference'] === ''` after removal.
            ($normalized['reference'] === '' && $normalized['transaction_id'] === '') ||
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
     * Authorization is membership of the reference in `_flizpay_transactions`.
     * Webhooks for legacy orders (plugin <= 2.5.3) are authorized through the
     * `_flizpay_issued_tx_ids` allowlist instead and validate against the
     * order's current amount and currency. Reconciliation always requires a
     * stored snapshot and a matching requested reference.
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

        $snapshot = $this->get_snapshot($order, $data['reference']);
        // Legacy (<= 2.5.3): becomes `$snapshot === null` after removal.
        if ($snapshot === null && !$this->legacy_is_authorized($order, $data, $source)) {
            return $this->reject('transaction_mismatch', 'Transaction is not authorized for this order.', $data);
        }

        if (!in_array($data['status'], array('completed', 'failed', 'canceled', 'pending', 'processing'), true)) {
            return $this->reject('unknown_status', 'Provider status is not recognized.', $data);
        }

        if ($source === 'reconciliation') {
            if ($snapshot === null || $requested_reference === null || $requested_reference === '') {
                return $this->reject('missing_reference', 'Transaction reference is unavailable.', $data);
            }

            if (!hash_equals($requested_reference, $data['reference'])) {
                return $this->reject('reference_mismatch', 'Transaction reference does not match.', $data);
            }
        }

        // Legacy (<= 2.5.3): legacy orders have no snapshot; validate against the
        // order itself. Drop the `??` fallbacks after removal.
        $expected_amount = $snapshot['original_amount'] ?? wc_format_decimal($order->get_total());
        $expected_currency = $snapshot['currency'] ?? strtoupper((string) $order->get_currency());
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
     * Read the transaction-time amount, currency, and attempt snapshot for a reference.
     *
     * @return TransactionSnapshot|null Stored snapshot, or null for unknown references.
     */
    private function get_snapshot(\WC_Order $order, string $reference): ?array
    {
        if ($reference === '') {
            return null;
        }

        $transactions = $order->get_meta('_flizpay_transactions');
        if (!is_array($transactions) || !isset($transactions[$reference]) || !is_array($transactions[$reference])) {
            return null;
        }

        $snapshot = $transactions[$reference];

        return array(
            'original_amount' => isset($snapshot['original_amount']) && is_numeric($snapshot['original_amount'])
                ? wc_format_decimal($snapshot['original_amount'])
                : wc_format_decimal($order->get_total()),
            'currency' => isset($snapshot['currency']) && is_scalar($snapshot['currency'])
                ? strtoupper(sanitize_text_field((string) $snapshot['currency']))
                : strtoupper((string) $order->get_currency()),
            'attempt' => isset($snapshot['attempt']) ? (int) $snapshot['attempt'] : 0,
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
            'completed' => '_flizpay_completed_reference',
            'failed' => '_flizpay_failed_reference',
            'canceled' => '_flizpay_canceled_reference',
        );
        // Legacy (<= 2.5.3): the reference guard exists because legacy payloads
        // may have an empty reference, which would match an unset marker.
        if ($data['reference'] !== '' && $order->get_meta($terminal_keys[$data['status']]) === $data['reference']) {
            return $this->noop('duplicate', 'Transaction was already processed.', $data);
        }

        // Legacy (<= 2.5.3): markers written by the old plugin hold transactionIds.
        if ($this->legacy_is_duplicate($order, $data)) {
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

        $snapshot = $this->get_snapshot($order, $data['reference']);
        if ($snapshot !== null && $snapshot['attempt'] < (int) $order->get_meta('_flizpay_transaction_attempt')) {
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
        $this->mark_terminal($order, 'completed', $data);
        $order->save();
        $order->payment_complete($this->identifier($data));
        $order->add_order_note('FLIZ reference: ' . $this->identifier($data));
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
        $this->mark_terminal($order, 'failed', $data);
        $this->advance_transaction_attempt($order);
        $order->save();
        $order->update_status('failed', __('Payment failed via FLIZpay', 'flizpay-for-woocommerce'));
        $order->add_order_note('FLIZ reference: ' . $this->identifier($data) . ' - payment failed');
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
        $this->mark_terminal($order, 'canceled', $data);
        $this->advance_transaction_attempt($order);
        $order->save();
        $order->update_status('cancelled', __('Payment canceled via FLIZpay', 'flizpay-for-woocommerce'));
        $order->add_order_note('FLIZ reference: ' . $this->identifier($data) . ' - payment canceled by user or bank');
        $order->save();

        return $this->applied('canceled', 'Order payment cancelled.', $data);
    }

    /**
     * Record the identifier that terminalized the order for duplicate detection.
     *
     * @param SettlementData $data
     */
    private function mark_terminal(\WC_Order $order, string $status, array $data): void
    {
        // Legacy (<= 2.5.3): webhooks without a reference use the legacy marker.
        if ($data['reference'] === '') {
            $this->legacy_mark_terminal($order, $status, $data);
            return;
        }

        $order->update_meta_data('_flizpay_' . $status . '_reference', $data['reference']);
    }

    /**
     * Identifier used for payment_complete() and order notes.
     *
     * @param SettlementData $data
     */
    private function identifier(array $data): string
    {
        // Legacy (<= 2.5.3): becomes plain `$data['reference']` after removal.
        return $data['reference'] !== '' ? $data['reference'] : $data['transaction_id'];
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
            'reference' => $data['reference'] ?? null,
            // Legacy (<= 2.5.3): only set for legacy webhooks.
            'transaction_id' => $data['transaction_id'] ?? null,
            'provider_status' => $data['status'] ?? null,
            'result' => $result,
        ));
    }

    // -------------------------------------------------------------------------
    // Legacy support: orders placed on plugin <= 2.5.3 and unpaid at upgrade.
    //
    // Their meta stores transactionIds (`_flizpay_issued_tx_ids` allowlist,
    // `_flizpay_*_tx` terminal markers) and no reference, so their webhooks
    // authorize and deduplicate through the methods below. Reconciliation never
    // uses them. Once such orders age out of the settlement window, delete this
    // section, the `transaction_id` field of SettlementData, and every call
    // site marked "Legacy (<= 2.5.3)".
    // -------------------------------------------------------------------------

    /**
     * Extract the deprecated transactionId carried by legacy webhook payloads.
     *
     * @param array<string, mixed> $data Raw provider payload.
     */
    private function legacy_transaction_id(array $data): string
    {
        $transaction_id = $data['transactionId'] ?? null;

        return is_scalar($transaction_id) ? sanitize_text_field((string) $transaction_id) : '';
    }

    /**
     * Authorize a webhook for a legacy order via the transactionId allowlist.
     *
     * @param SettlementData $data
     */
    private function legacy_is_authorized(\WC_Order $order, array $data, string $source): bool
    {
        if ($source !== 'webhook' || $data['transaction_id'] === '') {
            return false;
        }

        $issued = $order->get_meta('_flizpay_issued_tx_ids');

        return is_array($issued) && in_array($data['transaction_id'], $issued, true);
    }

    /**
     * Detect replays against the transactionId terminal markers.
     *
     * Without this check a replayed failed/canceled webhook would
     * re-terminalize a legacy order and advance the payment attempt twice.
     *
     * @param SettlementData $data
     */
    private function legacy_is_duplicate(\WC_Order $order, array $data): bool
    {
        if ($data['transaction_id'] === '') {
            return false;
        }

        $terminal_keys = array(
            'completed' => '_flizpay_completed_tx',
            'failed' => '_flizpay_failed_tx',
            'canceled' => '_flizpay_canceled_tx',
        );

        return isset($terminal_keys[$data['status']])
            && $order->get_meta($terminal_keys[$data['status']]) === $data['transaction_id'];
    }

    /**
     * Record the transactionId terminal marker for reference-less settlements.
     *
     * @param SettlementData $data
     */
    private function legacy_mark_terminal(\WC_Order $order, string $status, array $data): void
    {
        $order->update_meta_data('_flizpay_' . $status . '_tx', $data['transaction_id']);
    }
}
