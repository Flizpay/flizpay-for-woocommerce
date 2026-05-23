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

    // Check if rewrite rules need to be flushed
    if ($this->should_flush_rewrite_rules()) {
      flush_rewrite_rules();
      update_option('flizpay_rewrite_rules_flushed', true);
    }
  }

  private function should_flush_rewrite_rules()
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

  public function handle_webhook_request()
  {
    global $wp;

    if (isset($wp->query_vars['flizpay-webhook'])) {
      $data = $this->get_webhook_data();

      if (! $data) {
        wp_send_json_error('Invalid JSON', 422);
        return;
      }

      $authenticated = $this->webhook_authenticate($data);

      if (! $authenticated) {
        wp_send_json_error('Authentication failed', 401);
        return;
      }


      if (json_last_error() === JSON_ERROR_NONE && $authenticated) {
        if (isset($data['test'])) {
          $this->update_webhook_status(true);
          wp_send_json_success(array('alive' => true), 200);
        } else if (isset($data['updateCashbackInfo'])) {
          $this->update_cashback_info($data);
          wp_send_json_success('Cashback information updated', 200);
        } else {
          $this->finish_order($data);
          wp_send_json_success('Order updated successfully', 200);
        }
      } else {
        wp_send_json_error('Invalid Request', 422);
      }
    }

    return; // Do not process the request
  }

  public function finish_order($data)
  {
    if (!isset($data['metadata']['orderId']) || !isset($data['status'])) {
      wp_send_json_error('Missing order_id or status', 400);
    }

    $order_id = intval($data['metadata']['orderId']);
    $status = sanitize_text_field($data['status']);
    $incoming_tx = isset($data['transactionId']) ? sanitize_text_field((string) $data['transactionId']) : '';
    $order = wc_get_order($order_id);

    if (!$order) {
      wp_send_json_error('Order not found', 404);
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
  private function is_tx_authorized_for_order(\WC_Order $order, string $tx_id)
  {
    if (!is_string($tx_id) || $tx_id === '') {
      return false;
    }
    $issued = $order->get_meta('_flizpay_issued_tx_ids');
    if (!is_array($issued) || empty($issued)) {
      return false;
    }
    return in_array($tx_id, $issued, true);
  }

  public function webhook_authenticate($data)
  {
    $key = $this->gateway->get_option('flizpay_webhook_key');

    if (!is_string($key) || strlen($key) < 32) {
      return false;
    }

    if (!isset($_SERVER['HTTP_X_FLIZ_SIGNATURE'])) {
      return false;
    }

    $signature = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FLIZ_SIGNATURE']));
    $signedData = hash_hmac('sha256', wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $key);
    return hash_equals($signature, $signedData);
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

  private function fail_order(\WC_Order $order, $data)
  {
    $incoming_tx = isset($data['transactionId']) ? sanitize_text_field((string) $data['transactionId']) : '';

    if ($incoming_tx !== '' && $order->get_meta('_flizpay_failed_tx') === $incoming_tx) {
      return;
    }

    $order->update_status('failed', __('Payment failed via FLIZpay', 'flizpay-for-woocommerce'));

    if ($incoming_tx !== '') {
      $order->update_meta_data('_flizpay_failed_tx', $incoming_tx);
      $order->add_order_note('FLIZ transaction ID: ' . $incoming_tx . ' — payment failed');
    }
  }

  private function cancel_order(\WC_Order $order, $data)
  {
    $incoming_tx = isset($data['transactionId']) ? sanitize_text_field((string) $data['transactionId']) : '';

    if ($incoming_tx !== '' && $order->get_meta('_flizpay_canceled_tx') === $incoming_tx) {
      return;
    }

    $order->update_status('cancelled', __('Payment canceled via FLIZpay', 'flizpay-for-woocommerce'));

    if ($incoming_tx !== '') {
      $order->update_meta_data('_flizpay_canceled_tx', $incoming_tx);
      $order->add_order_note('FLIZ transaction ID: ' . $incoming_tx . ' — payment canceled by user or bank');
    }
  }

  private function complete_order($order, $data)
  {
    $incoming_tx = isset($data['transactionId']) ? sanitize_text_field((string) $data['transactionId']) : '';

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

    $fliz_discount = (float) $data['originalAmount'] - (float) $data['amount'];
    $cashback_value = (float) ($fliz_discount * 100) / $data['originalAmount'];

    if ($fliz_discount > 0) {
      $this->apply_cashback_discount($data, $order, $cashback_value, $fliz_discount, $data['currency']);
    }

    if ($incoming_tx !== '') {
      $order->add_order_note('FLIZ transaction ID: ' . $incoming_tx);
    }

    $this->send_order_emails($order->get_id());
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
    $order->calculate_totals(true);
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

  protected function get_webhook_data()
  {
    // Step 1: Read the raw POST body input (JSON or other) from the request.
    $raw = file_get_contents('php://input');

    // Step 2: Fallback for WordPress behavior where some requests might use form-encoded POST.
    if (isset($_POST['data'])) {
      $raw = stripslashes($_POST['data']);
    }

    // Step 3: Remove BOM (Byte Order Mark) if it exists at the start of the string.
    $raw = preg_replace('/^\x{FEFF}/u', '', $raw);

    // Step 4: Decode the JSON into an associative array.
    try {
      return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
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
