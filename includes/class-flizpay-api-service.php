<?php

declare(strict_types=1);

use Automattic\WooCommerce\Admin\Overrides\Order;

/**
 * Minimal client for authenticated FLIZpay API requests.
 */
class Flizpay_API_Service
{
    private string $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Generate the secret used to authenticate callbacks.
     *
     * @return string|null
     */
    public function get_webhook_key(): ?string
    {
        $client = WC_Flizpay_API::get_instance($this->api_key);

        $response = $client->dispatch("generate_webhook_key");

        return is_array($response) && isset($response["webhookKey"])
            ? (string) $response["webhookKey"]
            : null;
    }


    /**
     * Register the WooCommerce webhook URL for the merchant.
     *
     */
    public function generate_webhook_url(): ?string
    {
        $webhookUrl = home_url("/flizpay-webhook/", "https");

        if (
            str_contains($webhookUrl, "https://") === false &&
            str_contains($webhookUrl, "http://") === false
        ) {
            $webhookUrl = "https://" . $webhookUrl;
        }

        $client = WC_Flizpay_API::get_instance($this->api_key);

        $response = $client->dispatch(
            "edit_business",
            ["webhookUrl" => $webhookUrl]
        );

        $webhookUrlResponse = is_array($response) && isset($response["webhookUrl"])
            ? (string) $response["webhookUrl"]
            : null;

        if ($webhookUrlResponse === null || strcmp($webhookUrlResponse, $webhookUrl) !== 0) {
            return null;
        }

        return $webhookUrlResponse;
    }

    /**
     * Fetch normalized cashback percentages configured for the merchant.
     *
     * @return array{first_purchase_amount: float, standard_amount: float}
     */
    public function fetch_cashback_data(): ?array
    {
        $client = WC_Flizpay_API::get_instance($this->api_key);
        $response = $client->dispatch("fetch_cashback_data");

        if (
            is_array($response) &&
            isset($response["cashbacks"]) &&
            is_array($response["cashbacks"]) &&
            count($response["cashbacks"]) > 0
        ) {
            foreach ($response["cashbacks"] as $cashback) {
                $firstPurchaseAmount = floatval(
                    $cashback["firstPurchaseAmount"],
                );
                $amount = floatval($cashback["amount"]);

                if (
                    $cashback["active"] &&
                    ($firstPurchaseAmount > 0 || $amount > 0)
                ) {
                    return [
                        "first_purchase_amount" => $firstPurchaseAmount,
                        "standard_amount" => $amount,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Create one FLIZpay transaction without automatic retries.
     *
     * @param Order $order
     * @param string $source
     * @param string $idempotency_key
     * @return array{reference: string, redirectUrl: string}|null
     */
    public function create_transaction(Order $order, string $source, string $idempotency_key): ?array
    {
        $customer = [
            "email" => $order->get_billing_email(),
            "firstName" => $order->get_billing_first_name(),
            "lastName" => $order->get_billing_last_name(),
        ];
        $body = [
            "amount" => $order->get_total(),
            "currency" => $order->get_currency(),
            "externalId" => (string) $order->get_id(),
            "successUrl" => $order->get_checkout_order_received_url(),
            "failureUrl" => "https://checkout.flizpay.de/failed",
            "customer" => $customer,
            "source" => $source,
        ];
        $client = WC_Flizpay_API::get_instance($this->api_key);
        $response = $client->dispatch("create_transaction", $body, [
            "Idempotency-Key" => $idempotency_key,
        ]);

        if (!is_array($response)) {
            return null;
        }

        $this->handle_revoked_managed_connection((int) ($response['_http_status'] ?? 0));

        $redirect_url = isset($response["redirectUrl"])
            ? (string) $response["redirectUrl"]
            : null;
        $reference = isset($response["reference"])
            ? (string) $response["reference"]
            : "";

        if (!$redirect_url || $reference === "") {
            return null;
        }

        return [
            "reference" => $reference,
            "redirectUrl" => $redirect_url,
        ];
    }

    /**
     * Hide FLIZpay from checkout once FLIZpay stops accepting this shop's credentials.
     *
     * A managed connection can be released from the FLIZpay dashboard, which leaves the
     * shop holding a key the API no longer knows. Marking the connection dead means
     * is_available() drops FLIZpay from the next request instead of letting more
     * customers pick a payment method that can only fail.
     */
    private function handle_revoked_managed_connection(int $status_code): void
    {
        if ($status_code !== 401 && $status_code !== 403) {
            return;
        }

        $settings = get_option('woocommerce_flizpay_settings', array());
        if (!is_array($settings) || empty($settings['flizpay_managed_connection'])) {
            return;
        }

        if (($settings['flizpay_webhook_alive'] ?? '') === 'no') {
            return;
        }

        $settings['flizpay_webhook_alive'] = 'no';
        update_option('woocommerce_flizpay_settings', $settings, false);
    }

    /**
     * Retrieve and validate the merchant-facing transaction status response.
     *
     * Transport failures, authentication failures, missing transactions, server
     * failures, invalid JSON, and incomplete public responses are returned as
     * distinct result codes. This method never changes a WooCommerce order.
     *
     * @param string $reference Provider reference stored when the transaction was created.
     * @return array{success: bool, result: string, message: string, data?: array<string, mixed>}
     */
    public function get_transaction_status(string $reference): array
    {
        if ($reference === '') {
            return $this->status_error('missing_reference', 'Transaction reference is unavailable.');
        }

        $response = WC_Flizpay_API::get_instance($this->api_key)->dispatch(
            'get_transaction_status',
            array('reference' => $reference)
        );

        if (is_wp_error($response)) {
            return $this->status_error('network_error', 'Could not reach the FLIZpay status API.');
        }

        $status_code = is_array($response) ? (int) ($response['_http_status'] ?? 0) : 0;
        if ($status_code === 404) {
            return $this->status_error('not_found', 'FLIZpay transaction was not found.');
        }
        if ($status_code >= 500) {
            return $this->status_error('server_error', 'FLIZpay status API is unavailable.');
        }
        if ($status_code < 200 || $status_code >= 300) {
            return $this->status_error('api_error', 'FLIZpay status API returned an unexpected response.');
        }

        if (!is_array($response) || !empty($response['_json_error'])) {
            return $this->status_error('invalid_json', 'FLIZpay status response was not valid JSON.');
        }

        $data = $response;
        unset($data['_http_status']);
        $required = array('reference', 'status', 'amount', 'originalAmount', 'currency', 'orderId');
        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                return $this->status_error('invalid_response', 'FLIZpay status response is missing merchant transaction fields.');
            }
        }

        if (!is_scalar($data['reference']) || !hash_equals($reference, (string) $data['reference'])) {
            return $this->status_error('reference_mismatch', 'FLIZpay status response reference does not match the request.');
        }

        return array(
            'success' => true,
            'result' => 'success',
            'message' => 'Transaction status retrieved.',
            'data' => $data,
        );
    }

    /**
     * Build a non-mutating transaction status error result.
     *
     * @param string $result Stable machine-readable error code.
     * @param string $message Safe human-readable error description.
     * @return array{success: false, result: string, message: string}
     */
    private function status_error(string $result, string $message): array
    {
        return array('success' => false, 'result' => $result, 'message' => $message);
    }
}
