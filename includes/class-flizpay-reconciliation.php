<?php

declare(strict_types=1);

/**
 * Coordinates scheduled and manual reconciliation of pending FLIZpay orders.
 *
 * This class selects the transaction associated with the order's current payment
 * attempt, requests its latest provider status, and delegates all validation and
 * order mutation to Flizpay_Settlement.
 */
class Flizpay_Reconciliation
{
    public const HOOK = 'flizpay_reconciliation_scan';
    private const GROUP = 'flizpay';
    private const INTERVAL = 15 * MINUTE_IN_SECONDS;

    private Flizpay_API_Service $api_service;
    private Flizpay_Settlement $settlement;
    private string $api_key;

    /**
     * Create the reconciliation coordinator with its API and settlement dependencies.
     *
     * @param string $api_key Merchant API key used to decide whether scheduling is available.
     * @param Flizpay_API_Service $api_service Client used to retrieve provider transaction status.
     * @param Flizpay_Settlement $settlement Service responsible for validation and order transitions.
     */
    public function __construct(string $api_key, Flizpay_API_Service $api_service, Flizpay_Settlement $settlement)
    {
        $this->api_key = $api_key;
        $this->api_service = $api_service;
        $this->settlement = $settlement;
    }

    /**
     * Schedule one recurring reconciliation scan after Action Scheduler initializes.
     *
     * Scheduling is skipped when the merchant API key or Action Scheduler API is
     * unavailable. The hook and group lookup prevents duplicate recurring actions.
     */
    public function schedule(): void
    {
        if ($this->api_key === '' || !function_exists('as_next_scheduled_action') || !function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (as_next_scheduled_action(self::HOOK, array(), self::GROUP) === false) {
            as_schedule_recurring_action(time() + self::INTERVAL, self::INTERVAL, self::HOOK, array(), self::GROUP, true);
        }
    }

    /**
     * Process the oldest eligible FLIZpay orders in one scheduled batch.
     *
     * The query includes pending and checkout-draft orders created between 30
     * minutes and 10 days ago. Each order is handled independently so one API or
     * validation failure does not interrupt the remaining batch.
     */
    public function scan(): void
    {
        $orders = wc_get_orders(array(
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
            'status' => array('pending', 'checkout-draft'),
            'payment_method' => 'flizpay',
            'date_created' => gmdate('Y-m-d H:i:s', time() - (10 * DAY_IN_SECONDS))
                . '...'
                . gmdate('Y-m-d H:i:s', time() - (30 * MINUTE_IN_SECONDS)),
            'return' => 'objects',
        ));

        $this->log('info', 'FLIZpay reconciliation scan started.', array('candidate_count' => count($orders)));

        foreach ($orders as $order) {
            if ($order instanceof \WC_Order) {
                $this->reconcile_order($order, 'scheduled');
            }
        }
    }

    /**
     * Reconcile the transaction belonging to an order's current payment attempt.
     *
     * Orders that are no longer eligible are accepted as no-ops. Eligible orders
     * without a current reference return an error without mutation. Successful API
     * responses are passed unchanged to the settlement service, which performs the
     * authoritative ownership, amount, state, and race-protection checks.
     *
     * @param \WC_Order $order Order selected by the scanner or manual admin action.
     * @param string $context Invocation source used in logs: scheduled or manual.
     * @return array{success: bool, result: string, message: string, provider_status?: string}
     */
    public function reconcile_order(\WC_Order $order, string $context = 'scheduled'): array
    {
        if (!$this->is_eligible($order)) {
            return $this->record($context, $order, $this->result(true, 'not_eligible', 'Order no longer requires reconciliation.'));
        }

        $reference = $this->get_current_reference($order);
        if ($reference === null) {
            return $this->record($context, $order, $this->result(false, 'missing_reference', 'Current transaction reference is unavailable.'));
        }

        $this->log('info', 'Checking FLIZpay transaction status.', array(
            'order_id' => $order->get_id(),
            'reference' => $reference,
            'context' => $context,
        ));

        $response = $this->api_service->get_transaction_status($reference);
        if (!$response['success'] || !isset($response['data'])) {
            return $this->record($context, $order, $this->result(false, $response['result'], $response['message']));
        }

        $provider_status = is_scalar($response['data']['status'] ?? null)
            ? sanitize_text_field((string) $response['data']['status'])
            : '';
        $this->log('info', 'FLIZpay transaction status received.', array(
            'order_id' => $order->get_id(),
            'reference' => $reference,
            'provider_status' => $provider_status,
            'context' => $context,
        ));

        $settlement = $this->settlement->settle($response['data'], 'reconciliation', $reference);
        $settlement['provider_status'] = $provider_status;

        return $this->record($context, $order, $settlement);
    }

    /**
     * Check FLIZpay before WooCommerce cancels an unpaid pending order.
     *
     * Orders with a current FLIZpay transaction are reconciled first. WooCommerce
     * cancellation proceeds for pending, failed, and canceled transactions so stock
     * is not held after the merchant's configured timeout. Processing transactions
     * and uncertain API or validation results block cancellation. Completed
     * transactions are settled as paid before WooCommerce can cancel them. Orders
     * without a current reference retain normal WooCommerce cancellation behavior.
     *
     * @param bool $should_cancel Whether WooCommerce currently permits cancellation.
     * @param \WC_Order $order Pending order selected by WooCommerce cleanup.
     * @return bool True only when normal WooCommerce cancellation should continue.
     */
    public function should_cancel_unpaid_order(bool $should_cancel, \WC_Order $order): bool
    {
        if (
            !$should_cancel
            || $order->get_payment_method() !== 'flizpay'
            || !$order->has_status('pending')
            || $order->is_paid()
        ) {
            return $should_cancel;
        }

        if ($this->get_current_reference($order) === null) {
            $this->log('info', 'WooCommerce unpaid-order cancellation allowed: no current FLIZpay reference.', array(
                'order_id' => $order->get_id(),
                'context' => 'unpaid-cancellation',
            ));
            return true;
        }

        $result = $this->reconcile_order($order, 'unpaid-cancellation');
        $fresh_order = wc_get_order($order->get_id());
        $allow_cancellation = $result['success']
            && in_array($result['provider_status'] ?? '', array('pending', 'failed', 'canceled'), true)
            && $fresh_order instanceof \WC_Order
            && !$fresh_order->is_paid();

        $this->log('info', 'WooCommerce unpaid-order cancellation decision made after FLIZpay status check.', array(
            'order_id' => $order->get_id(),
            'context' => 'unpaid-cancellation',
            'result' => $result['result'],
            'provider_status' => $result['provider_status'] ?? '',
            'decision' => $allow_cancellation ? 'allowed' : 'blocked',
        ));

        return $allow_cancellation;
    }

    /**
     * Reconcile an unpaid FLIZpay order when the customer reaches the thank-you page.
     *
     * This provides immediate recovery when the payment-complete webhook was blocked
     * or delayed. Paid, terminal, non-FLIZpay, and legacy orders without a stored
     * current reference are ignored. Settlement remains idempotent when a webhook
     * reaches the store concurrently.
     *
     * @param int $order_id WooCommerce order displayed on the thank-you page.
     */
    public function reconcile_on_thankyou($order_id): void
    {
        $order = wc_get_order((int) $order_id);
        if (
            !$order instanceof \WC_Order
            || !$this->is_eligible($order)
            || $this->get_current_reference($order) === null
        ) {
            return;
        }

        $this->reconcile_order($order, 'thankyou');
    }

    /**
     * Add the manual status-check action to an eligible order's action menu.
     *
     * Some WooCommerce screens pass the order as the second filter argument while
     * legacy screens expose only the order ID in the request, so both are supported.
     *
     * @param array<string, string> $actions Existing WooCommerce order actions.
     * @param \WC_Order|null $order Order supplied by WooCommerce when available.
     * @return array<string, string> Actions with the FLIZpay check appended when eligible.
     */
    public function add_order_action(array $actions, ?\WC_Order $order = null): array
    {
        if ($order === null) {
            $order_id = isset($_GET['post']) ? absint(wp_unslash($_GET['post'])) : 0;
            $order = $order_id > 0 ? wc_get_order($order_id) : null;
        }

        if (!$order instanceof \WC_Order) {
            return $actions;
        }

        if ($this->is_eligible($order) && $this->get_current_reference($order) !== null) {
            $actions['flizpay_check_status'] = __('Check FLIZpay status', 'flizpay-for-woocommerce');
        }

        return $actions;
    }

    /**
     * Execute a synchronous manual reconciliation from the order actions menu.
     *
     * The same reconciliation path as the scheduled scan is used. A concise order
     * note records the result for administrators regardless of success or failure.
     *
     * @param \WC_Order $order Order whose current transaction should be checked.
     */
    public function handle_order_action(\WC_Order $order): void
    {
        $result = $this->reconcile_order($order, 'manual');
        $prefix = $result['success'] ? 'FLIZpay status check: ' : 'FLIZpay status check failed: ';
        $fresh_order = wc_get_order($order->get_id());
        if ($fresh_order instanceof \WC_Order) {
            $fresh_order->add_order_note($prefix . $result['message']);
        }
    }

    /**
     * Determine whether an order still requires a provider status check.
     *
     * @param \WC_Order $order Order being considered for reconciliation.
     * @return bool True only for unpaid FLIZpay orders in a non-terminal checkout state.
     */
    private function is_eligible(\WC_Order $order): bool
    {
        return $order->get_payment_method() === 'flizpay'
            && !$order->is_paid()
            && $order->has_status(array('pending', 'checkout-draft'));
    }

    /**
     * Find the stored reference for the current payment attempt.
     *
     * References from older attempts are ignored. Iteration starts with the most
     * recently stored entry in case malformed metadata contains multiple entries
     * for the same attempt. Legacy orders created by plugin <= 2.5.3 never
     * stored a reference and return null, so they are skipped by every
     * reconciliation entry point.
     *
     * @param \WC_Order $order Order containing FLIZpay attempt metadata.
     * @return string|null Current reference, or null when unavailable.
     */
    private function get_current_reference(\WC_Order $order): ?string
    {
        $attempt = (int) $order->get_meta('_flizpay_transaction_attempt');
        $transactions = $order->get_meta('_flizpay_transactions');
        if (!is_array($transactions)) {
            return null;
        }

        foreach (array_reverse($transactions, true) as $reference => $snapshot) {
            if (
                !is_string($reference)
                || !is_array($snapshot)
                || (int) ($snapshot['attempt'] ?? -1) !== $attempt
            ) {
                continue;
            }

            $value = sanitize_text_field($reference);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Log a reconciliation outcome and return it unchanged to the caller.
     *
     * @param string $context Invocation source used in logs.
     * @param \WC_Order $order Reconciled order.
     * @param array{success: bool, result: string, message: string, provider_status?: string} $result Outcome to record.
     * @return array{success: bool, result: string, message: string, provider_status?: string} The original outcome.
     */
    private function record(string $context, \WC_Order $order, array $result): array
    {
        $this->log($result['success'] ? 'info' : 'warning', 'FLIZpay reconciliation result.', array(
            'order_id' => $order->get_id(),
            'context' => $context,
            'result' => $result['result'],
        ));

        return $result;
    }

    /**
     * Build the standard result consumed by scheduled and manual callers.
     *
     * @param bool $success Whether reconciliation completed without an actionable error.
     * @param string $result Stable machine-readable outcome code.
     * @param string $message Human-readable result used in manual order notes.
     * @return array{success: bool, result: string, message: string}
     */
    private function result(bool $success, string $result, string $message): array
    {
        return array('success' => $success, 'result' => $result, 'message' => $message);
    }

    /**
     * Write a sanitized reconciliation event to the WooCommerce logger.
     *
     * @param string $level WooCommerce logger severity.
     * @param string $message Log message without customer or credential data.
     * @param array<string, mixed> $context Sanitized identifiers and result fields.
     */
    private function log(string $level, string $message, array $context = array()): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->log($level, $message, array_merge(array('source' => 'flizpay-reconciliation'), $context));
    }
}
