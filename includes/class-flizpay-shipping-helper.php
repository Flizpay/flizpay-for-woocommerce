<?php

class Flizpay_Shipping_Helper
{
  private $gateway;

  public function __construct($gateway)
  {
    $this->gateway = $gateway;
  }

  public function calculate_shipping($data)
  {
    if (!isset($data['orderId']) || !isset($data['shippingInfo'])) {
      return wp_send_json_error('OrderId and Shipping Info are required', 400);
    }

    $order_id = $data['orderId'];
    $shipping_info = $data['shippingInfo'];

    $order = wc_get_order($order_id);
    if (!$order) {
      return wp_send_json_error('Invalid order ID', 404);
    }

    $address = $this->get_address_from_shipping_info($shipping_info);
    $this->update_order_address($order, $address);

    $contents = $this->get_order_contents($order);

    $package = $this->create_shipping_package($address, $contents, $order);

    return $this->get_available_shipping_methods($package);
  }

  public function set_shipping_method($data)
  {
    if (!isset($data['orderId']) || !isset($data['shippingMethodId'])) {
      return wp_send_json_error('OrderId and ShippingMethodId are required', 400);
    }

    $order_id = $data['orderId'];
    $shipping_method_id = $data['shippingMethodId'];

    $order = wc_get_order($order_id);
    if (!$order) {
      return wp_send_json_error('Invalid order ID', 404);
    }

    $contents = $this->get_order_contents($order);

    $package = $this->create_shipping_package($order->get_address('shipping'), $contents, $order);

    return $this->apply_selected_shipping_method($order, $package, $shipping_method_id);
  }

  private function get_address_from_shipping_info($shipping_info)
  {
    return [
      'first_name' => $shipping_info['firstName'],
      'last_name' => $shipping_info['lastName'],
      'company' => '',
      'address_1' => $shipping_info['street'] . ' ' . $shipping_info['number'],
      'address_2' => '',
      'city' => $shipping_info['city'],
      'state' => '',
      'postcode' => $shipping_info['zipCode'],
      'country' => 'DE',
      'email' => $shipping_info['email']
    ];
  }

  private function update_order_address($order, $address)
  {
    $order->set_address($address, 'shipping');
    $order->set_address($address, 'billing');
    $order->set_billing_first_name($address['first_name']);
    $order->set_billing_last_name($address['last_name']);
    $order->set_billing_email($address['email']);
    $order->save();
  }

  private function get_order_contents($order)
  {
    $contents = [];

    foreach ($order->get_items() as $item_id => $item) {
      $product = $item->get_product();
      if ($product) {
        $contents[$item_id] = [
          'product_id' => $product->get_id(),
          'variation_id' => $product->is_type('variable') ? $product->get_id() : 0,
          'quantity' => $item->get_quantity(),
          'data' => $product,
        ];
      }
    }

    return $contents;
  }

  private function create_shipping_package($address, $contents, $order)
  {
    return [
      'destination' => [
        'country' => $address['country'],
        'state' => $address['state'],
        'postcode' => $address['postcode'],
        'city' => $address['city'],
        'address' => $address['address_1'],
      ],
      'contents' => $contents,
      'contents_cost' => $order->get_total(),
      'applied_coupons' => $order->get_coupon_codes(),
    ];
  }

  private function get_available_shipping_methods($package)
  {
    $prices_include_tax = ('yes' === get_option('woocommerce_prices_include_tax'));

    $shipping = new WC_Shipping();
    $shipping_packages = $shipping->calculate_shipping_for_package($package);

    $available_methods = [];
    foreach ($shipping_packages['rates'] as $rate_id => $rate) {
      $shipping_cost_incl_tax = $this->calculate_shipping_cost_incl_tax($rate, $prices_include_tax);
      $available_methods[] = [
        'name' => $rate->get_label(),
        'totalCost' => $shipping_cost_incl_tax,
        'id' => $rate_id,
      ];
    }

    return $available_methods;
  }

  private function calculate_shipping_cost_incl_tax($rate, $prices_include_tax)
  {
    if (!$prices_include_tax) {
      $tax_rates = WC_Tax::get_shipping_tax_rates();
      $calculated_taxes = WC_Tax::calc_shipping_tax($rate->get_cost(), $tax_rates);
      return (float) $rate->get_cost() + array_sum($calculated_taxes);
    } else {
      return (float) $rate->get_cost();
    }
  }

  private function apply_selected_shipping_method($order, $package, $shipping_method_id)
  {
    $shipping = new WC_Shipping();
    $shipping_packages = $shipping->calculate_shipping_for_package($package);

    $selected_method = $this->find_selected_shipping_method($shipping_packages, $shipping_method_id);

    if (!$selected_method) {
      return wp_send_json_error('Invalid shipping method ID', 400);
    }

    $this->remove_existing_shipping_items($order);
    $this->add_selected_shipping_method($order, $selected_method);

    $order->calculate_totals();
    $order->save();

    return $order->get_total();
  }

  private function find_selected_shipping_method($shipping_packages, $shipping_method_id)
  {
    foreach ($shipping_packages['rates'] as $rate_id => $rate) {
      if ($rate_id === $shipping_method_id) {
        return $rate;
      }
    }
    return null;
  }

  private function remove_existing_shipping_items($order)
  {
    $order->remove_order_items('shipping');
  }

  private function add_selected_shipping_method($order, $selected_method)
  {
    $item = new WC_Order_Item_Shipping();
    $item->set_method_id($selected_method->get_method_id());
    $item->set_method_title($selected_method->get_label());
    $item->set_total($this->calculate_shipping_cost_incl_tax($selected_method, ('yes' === get_option('woocommerce_prices_include_tax'))));
    $item->save();

    $order->add_item($item);
  }
}
