<?php

class Flizpay_Cashback_Helper
{
  private $gateway;

  private $is_german;

  public function __construct($gateway)
  {
    $this->gateway = $gateway;
    $this->is_german = str_contains(get_locale(), 'de');
  }

  public function set_cashback_info()
  {
    $display_value = $this->get_display_value();
    if ($this->is_cashback_available()) {
      $shop_name = get_bloginfo('name');
      $title = sprintf(__('cashback-title', 'flizpay-for-woocommerce'), $display_value);
      $description = $this->get_cashback_description($shop_name);
    } else {
      $title = __('title', 'flizpay-for-woocommerce');
      $description = __('description', 'flizpay-for-woocommerce');
    }

    // Use commas for german
    if ($this->is_cashback_available() && $this->is_german) {
      $title = str_replace('.', ',', $title);
    }

    $this->gateway->title = $this->gateway->flizpay_display_headline === 'yes' ? $title : 'FLIZpay';
    $this->gateway->description = $this->gateway->flizpay_display_description === 'yes' ? $description : null;
  }

  public function set_title()
  {

    if ($this->is_default_translation($this->gateway->title)) {
      $cashback_value = str_replace('.', ',', (string) $this->get_display_value());

      if ($this->gateway->flizpay_display_headline === 'yes') {
        $this->gateway->title = !is_null($this->gateway->cashback)
          ? "FLIZpay - Bis zu $cashback_value% Rabatt"
          : 'FLIZpay - Die Zahlungsrevolution';
      } else {
        $this->gateway->title = 'FLIZpay';
      }
    }
    $this->gateway->update_option('title', $this->gateway->title);
  }

  public function set_description()
  {
    if ($this->is_default_translation($this->gateway->description)) {
      if ($this->gateway->flizpay_display_description === 'yes') {
        $this->gateway->description = 'Sichere Zahlungen in direkter Zusammenarbeit mit deiner Bank, deine Daten bleiben privat und in Deutschland, und du unterstützt mit FLIZpay kleine Unternehmen.';
      }
    }
    $this->gateway->update_option('description', $this->gateway->description);
  }

  private function get_display_value()
  {
    if (!$this->gateway->cashback)
      return null;

    if (floatval($this->gateway->cashback['first_purchase_amount']) > 0 || floatval($this->gateway->cashback['standard_amount']) > 0) {
      return max(floatval($this->gateway->cashback['first_purchase_amount']), floatval($this->gateway->cashback['standard_amount']));
    }
  }

  private function get_cashback_description($shop_name)
  {
    switch ($this->get_cashback_type()) {
      case 'both':
        return sprintf(
          __('cashback-description-both', 'flizpay-for-woocommerce'),
          $shop_name,
          $this->gateway->cashback['standard_amount']
        );
      case 'first':
        return sprintf(
          __('cashback-description-first', 'flizpay-for-woocommerce'),
          $shop_name
        );
      case 'standard':
        return sprintf(
          __('cashback-description-standard', 'flizpay-for-woocommerce'),
          $this->gateway->cashback['standard_amount'],
          $shop_name
        );
    }
  }

  private function get_cashback_type()
  {
    $first = floatval($this->gateway->cashback['first_purchase_amount']);
    $amount = floatval($this->gateway->cashback['standard_amount']);

    if ($first > 0 && $amount > 0)
      return 'both';
    else if ($first > 0)
      return 'first';
    else
      return 'standard';
  }

  private function is_cashback_available()
  {
    if ($this->gateway->flizpay_webhook_alive !== 'yes')
      return false;

    if (!isset($this->gateway->webhook_key))
      return false;

    if (!isset($this->gateway->webhook_url))
      return false;

    if (!isset($this->gateway->cashback))
      return false;

    if (
      !isset($this->gateway->cashback['first_purchase_amount']) &&
      !isset($this->gateway->cashback['standard_amount'])
    )
      return false;

    return true;
  }

  private function is_default_translation($value)
  {
    $fallbacks = [
      'cashback-title',
      'cashback-description-both',
      'cashback-description-first',
      'cashback-description-standard',
      'title',
      'description'
    ];

    return in_array($value, $fallbacks);
  }
}
