<?php

class Flizpay_Webhook_Helper
{
  private $gateway;

  public function __construct($gateway)
  {
    $this->gateway = $gateway;
  }

  public function register_webhook_endpoint()
  {
    add_rewrite_tag('%flizpay-webhook%', '([^&]+)');
    add_rewrite_rule('^flizpay-webhook/?', 'index.php?flizpay-webhook=1&source=woocommerce', 'top');

    if (empty($this->gateway->get_option('flizpay_webhook_key'))) {
      flush_rewrite_rules();
    }
  }

  public function handle_webhook_request()
  {
    global $wp;

    // Get logger instance
    $logger = Flizpay_Logger::get_instance();

    if (isset($wp->query_vars['flizpay-webhook'])) {
      $logger->info('Webhook request received');

      $data = json_decode(file_get_contents('php://input'), true);
      $is_valid_json = json_last_error() === JSON_ERROR_NONE;
      $payload_type = $this->determine_webhook_type($data);
      $logger->info('Webhook payload received', array(
        'is_valid_json' => $is_valid_json,
        'payload_type' => $is_valid_json ? $payload_type : 'invalid'
      ));

      if ($is_valid_json && $this->webhook_authenticate($data)) {
        $logger->info('Webhook authentication successful');

        if ($payload_type === 'test') {
          $logger->info('Webhook test received');
          $this->update_webhook_status(true);
          wp_send_json_success(array('alive' => true), 200);
        } else if ($payload_type === 'shippingInfo') {
          $logger->info('Shipping info request received', array('shipping_info' => isset($data['shippingInfo']) ? 'present' : 'missing'));
          $shipping_info = $this->gateway->calculate_shipping($data);
          wp_send_json_success($shipping_info, 200);
        } else if ($payload_type === 'shippingMethodId') {
          $logger->info('Shipping method selection received', array('shipping_method_id' => $data['shippingMethodId']));
          $total_cost = $this->gateway->set_shipping_method($data);
          wp_send_json_success(array('totalCost' => $total_cost), 200);
        } else if ($payload_type === 'updateCashbackInfo') {
          $logger->info('Cashback info update received', array(
            'first_purchase_amount' => $data['firstPurchaseAmount'],
            'amount' => $data['amount'],
          ));
          $this->update_cashback_info($data);
          wp_send_json_success('Cashback information updated', 200);
        } else {
          $logger->info('Order completion webhook received', array(
            'transaction_id' => isset($data['transactionId']) ? $data['transactionId'] : 'missing',
            'status' => isset($data['status']) ? $data['status'] : 'missing'
          ));
          $this->finish_order($data);
          wp_send_json_success('Order updated successfully', 200);
        }
      } else {
        $logger->error('Webhook authentication failed or invalid JSON payload');
        wp_send_json_error('Invalid Request', 422);
      }
    }

    return; // Do not process the request
  }

  /**
   * Determine the type of webhook for logging purposes
   * 
   * @param array $data The webhook payload
   * @return string The type of webhook
   */
  private function determine_webhook_type($data)
  {
    if (isset($data['test'])) {
      return 'test';
    } else if (isset($data['shippingInfo'])) {
      return 'shipping_info';
    } else if (isset($data['shippingMethodId'])) {
      return 'shipping_method';
    } else if (isset($data['updateCashbackInfo'])) {
      return 'cashback_info';
    } else if (isset($data['status']) && isset($data['metadata']['orderId'])) {
      return 'order_completion';
    }

    return 'unknown';
  }

  public function finish_order($data)
  {
    if (!isset($data['metadata']['orderId']) || !isset($data['status'])) {
      wp_send_json_error('Missing order_id or status', 400);
    }

    $order_id = intval($data['metadata']['orderId']);
    $status = sanitize_text_field($data['status']);
    $order = wc_get_order($order_id);

    if (!$order) {
      wp_send_json_error('Order not found', 404);
    }

    if ($status === 'completed') {
      $this->complete_order($order, $data);
    }

    $order->save();
  }

  public function webhook_authenticate($data)
  {
    $key = $this->gateway->get_option('flizpay_webhook_key');

    if (isset($_SERVER['HTTP_X_FLIZ_SIGNATURE'])) {
      $signature = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FLIZ_SIGNATURE']));
      $signedData = hash_hmac('sha256', wp_json_encode($data, JSON_UNESCAPED_UNICODE), $key);
      return hash_equals($signature, $signedData);
    }
  }

  private function update_webhook_status($status)
  {
    $this->gateway->update_option('flizpay_webhook_alive', $status ? 'yes' : 'no');
    $this->gateway->update_option('flizpay_enabled', $status ? 'yes' : 'no');
    $this->gateway->update_option('enabled', $status ? 'yes' : 'no');
  }

  private function update_cashback_info($data)
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

  private function complete_order($order, $data)
  {
    // Get logger instance
    $logger = Flizpay_Logger::get_instance();
    $logger->info('Completing order', array(
      'order_id' => $order->get_id(),
      'transaction_id' => $data['transactionId'],
      'original_amount' => isset($data['originalAmount']) ? $data['originalAmount'] : 'not_set',
      'amount' => isset($data['amount']) ? $data['amount'] : 'not_set'
    ));

    $order->payment_complete($data['transactionId']);
    $logger->info('Order marked as payment complete', array('order_id' => $order->get_id()));

    $fliz_discount = (float) $data['originalAmount'] - (float) $data['amount'];
    $cashback_value = (float) ($fliz_discount * 100) / $data['originalAmount'];

    if ($fliz_discount > 0) {
      $logger->info('Applying cashback discount', array(
        'order_id' => $order->get_id(),
        'fliz_discount' => $fliz_discount,
        'cashback_percentage' => $cashback_value,
        'currency' => $data['currency']
      ));
      $this->apply_cashback_discount($data, $order, $cashback_value, $fliz_discount, $data['currency']);
    }

    if (isset($data['transactionId'])) {
      $order->add_order_note('FLIZ transaction ID: ' . sanitize_text_field($data['transactionId']));
    }

    $logger->info('Sending order emails', array('order_id' => $order->get_id()));
    $this->send_order_emails($order->get_id());
    $logger->info('Order completion process finished', array('order_id' => $order->get_id()));
  }

  private function apply_cashback_discount($data, $order, $cashback_value, $fliz_discount, $currency)
  {
    $line_items = $order->get_items();
    $shipping_items = $order->get_items('shipping');

    foreach ($line_items as $item) {
      $item_subtotal = $item->get_total();
      $discount_amount_fliz = ($item_subtotal * $cashback_value) / 100;
      $new_total = round($item_subtotal - $discount_amount_fliz, 2, PHP_ROUND_HALF_DOWN);
      $item->set_total($new_total);
      $item->save();
    }

    foreach ($shipping_items as $shipping) {
      $shipping_total = $shipping->get_total();
      $discount_amount_fliz = ($shipping_total * $cashback_value) / 100;
      $new_shipping_total = round($shipping_total - $discount_amount_fliz, 2, PHP_ROUND_HALF_DOWN);
      $shipping->set_total($new_shipping_total);
      $shipping->save();
    }

    $order->calculate_taxes();
    $order->calculate_totals();
    $order->set_total($data['amount']);
    $order->add_order_note('FLIZ Cashback Applied: ' . $currency . sanitize_text_field($fliz_discount));
    WC()->cart->empty_cart();
  }

  private function send_order_emails($order_id)
  {
    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();

    if (!empty($emails['WC_Email_Customer_Completed_Order'])) {
      $emails['WC_Email_Customer_Completed_Order']->trigger($order_id);
    }
    if (!empty($emails['WC_Email_Customer_Invoice'])) {
      $emails['WC_Email_Customer_Invoice']->trigger($order_id);
    }
    if (!empty($emails['WC_Email_New_Order'])) {
      $emails['WC_Email_New_Order']->trigger($order_id);
    }
  }
}
