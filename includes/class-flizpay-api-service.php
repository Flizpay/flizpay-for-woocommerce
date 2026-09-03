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
     * @return array{cashback: array|null, webhook_key: string, webhook_url: string}|WP_Error
     */
    public function establish_connection(): array|WP_Error
    {
        try {
            $webhook_url = $this->generate_webhook_url();
            $webhook_key = $this->get_webhook_key();
            $cashback_data = $this->fetch_cashback_data();
        } catch (\Exception $e) {
            Flizpay_Sentry::with_scope(static function ($scope) use ($e): void {
                if ($scope && method_exists($scope, 'setExtras')) {
                    $scope->setExtras([
                        'function_name' => 'establish_connection',
                        'message' => 'Exception occurred while establishing connection to FLIZpay',
                        'plugin_version' => FLIZPAY_VERSION,
                    ]);
                }

                Flizpay_Sentry::capture_exception($e);
            });

            return new WP_Error('flizpay_connection_failed', $e->getMessage());
        }

        if (!$webhook_url || !$webhook_key) {
            return new WP_Error('flizpay_connection_failed', 'FLIZpay did not return a usable webhook configuration.');
        }

        return array(
            'cashback' => $cashback_data,
            'webhook_key' => $webhook_key,
            'webhook_url' => $webhook_url,
        );
    }

    /**
     * Describe the FLIZpay account a connect token belongs to, without redeeming it.
     *
     * @return string|WP_Error Display label for the account, or an error.
     */
    public static function preview_pairing(string $token, string $shop_url)
    {
        $response = WC_Flizpay_API::get_instance('')->dispatch(
            'pairing_preview',
            array('shopUrl' => $shop_url, 'token' => $token)
        );

        if (is_wp_error($response)) {
            return new WP_Error('flizpay_connect_unreachable', __('FLIZpay could not be reached.', 'flizpay-for-woocommerce'));
        }

        $status = is_array($response) ? (int) ($response['_http_status'] ?? 0) : 0;
        if ($status < 200 || $status >= 300) {
            $message = is_array($response) && !empty($response['message'])
                ? sanitize_text_field($response['message'])
                : __('FLIZpay rejected the connection.', 'flizpay-for-woocommerce');
            return new WP_Error('flizpay_connect_failed', $message);
        }

        $account_email = is_array($response) && isset($response['accountEmail'])
            ? sanitize_text_field((string) $response['accountEmail'])
            : '';
        $account_name = is_array($response) && isset($response['accountName'])
            ? sanitize_text_field((string) $response['accountName'])
            : '';

        if ($account_email === '') {
            return new WP_Error('flizpay_connect_failed', __('FLIZpay did not identify the account behind this link.', 'flizpay-for-woocommerce'));
        }

        return $account_name === '' ? $account_email : sprintf('%s (%s)', $account_name, $account_email);
    }

    /**
     * @return string|WP_Error
     */
    public static function exchange_pairing(string $token, string $shop_url)
    {
        $response = WC_Flizpay_API::get_instance('')->dispatch(
            'pairing_exchange',
            array('shopUrl' => $shop_url, 'token' => $token)
        );

        if (is_wp_error($response)) {
            return new WP_Error('flizpay_connect_unreachable', __('FLIZpay could not be reached.', 'flizpay-for-woocommerce'));
        }

        $status = is_array($response) ? (int) ($response['_http_status'] ?? 0) : 0;
        $api_key = is_array($response) && isset($response['apiKey']) ? (string) $response['apiKey'] : '';

        if ($status < 200 || $status >= 300 || $api_key === '') {
            $message = is_array($response) && !empty($response['message'])
                ? sanitize_text_field($response['message'])
                : __('FLIZpay rejected the connection.', 'flizpay-for-woocommerce');
            return new WP_Error('flizpay_connect_failed', $message);
        }

        return $api_key;
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
        // Local/dev sites may only serve plain http; the FLIZpay backend allows
        // http webhooks for local development hosts, so only force https elsewhere.
        $is_local_environment = in_array(
            wp_get_environment_type(),
            ["local", "development"],
            true,
        );
        $webhookUrl = home_url(
            "/flizpay-webhook/",
            $is_local_environment ? null : "https",
        );

        if (
            str_contains($webhookUrl, "https://") === false &&
            str_contains($webhookUrl, "http://") === false
        ) {
            $webhookUrl = "https://" . $webhookUrl;
        }

        $client = WC_Flizpay_API::get_instance($this->api_key);

        $response = $client->dispatch(
            "edit_business",
            ["webhookUrl" => $webhookUrl, "integrationType" => "WooCommerce"]
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
