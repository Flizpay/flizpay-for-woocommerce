<?php

class Flizpay_API_Service
{
  private $api_key;

  public function __construct($api_key)
  {
    $this->api_key = $api_key;
  }

  public function get_webhook_key()
  {
    $client = WC_Flizpay_API::get_instance($this->api_key);

    $response = $client->dispatch('generate_webhook_key', null, false);

    return $response['webhookKey'] ?? null;
  }

  public function generate_webhook_url()
  {
    $webhookUrl = home_url('/flizpay-webhook?flizpay-webhook=1&source=woocommerce');

    $client = WC_Flizpay_API::get_instance($this->api_key);

    $response = $client->dispatch('edit_business', array('webhookUrl' => $webhookUrl), false);

    $webhookUrlResponse = $response['webhookUrl'] ?? null;

    if (strcmp($webhookUrlResponse, $webhookUrl) !== 0) {
      return null;
    }

    return $webhookUrlResponse;
  }

  public function fetch_cashback_data()
  {
    $client = WC_Flizpay_API::get_instance($this->api_key);
    $response = $client->dispatch('fetch_cashback_data', null, false);

    if (isset($response['cashbacks']) && count($response['cashbacks']) > 0) {
      foreach ($response['cashbacks'] as $cashback) {
        $firstPurchaseAmount = floatval($cashback['firstPurchaseAmount']);
        $amount = floatval($cashback['amount']);

        if ($cashback['active'] && ($firstPurchaseAmount > 0 || $amount > 0)) {
          return [
            'first_purchase_amount' => $firstPurchaseAmount,
            'standard_amount' => $amount
          ];
        }
      }
    }

    return null;
  }

  public function create_transaction($order, $source)
  {
    $customer = [
      'email' => $order->get_billing_email(),
      'firstName' => $order->get_billing_first_name(),
      'lastName' => $order->get_billing_last_name()
    ];
    $body = [
      'amount' => $order->get_total(),
      'currency' => $order->get_currency(),
      'externalId' => $order->get_id(),
      'successUrl' => $order->get_checkout_order_received_url(),
      'failureUrl' => 'https://checkout.flizpay.de/failed',
      'customer' => $customer,
      'source' => $source
    ];
    $client = WC_Flizpay_API::get_instance($this->api_key);
    $response = $client->dispatch('create_transaction', $body, false);

    return $response['redirectUrl'] ?? null;
  }
}
