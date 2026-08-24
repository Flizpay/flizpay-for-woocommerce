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

        $response = $client->dispatch("generate_webhook_key", null, false);

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
            ["webhookUrl" => $webhookUrl],
            false,
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
        $response = $client->dispatch("fetch_cashback_data", null, false);

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
     * @return array{transactionId: string, redirectUrl: string}|null
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
        $response = $client->dispatch("create_transaction", $body, false, [
            "Idempotency-Key" => $idempotency_key,
        ]);

        if (!is_array($response)) {
            return null;
        }

        $redirect_url = isset($response["redirectUrl"])
            ? (string) $response["redirectUrl"]
            : null;
        $transaction_id = isset($response["transactionId"])
            ? (string) $response["transactionId"]
            : "";

        if (!$redirect_url || $transaction_id === "") {
            return null;
        }

        return [
            "redirectUrl" => $redirect_url,
            "transactionId" => $transaction_id,
        ];
    }
}
