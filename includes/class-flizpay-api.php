<?php

class WC_Flizpay_API
{
  private $api_key;
  private $base_url;
  private $routes;

  public static $instance;

  public static function get_instance($api_key)
  {
    if (!isset(self::$instance)) {
      self::$instance = new WC_Flizpay_API($api_key);
    }
    return self::$instance;
  }

  private function __construct($api_key)
  {
    $this->api_key = $api_key;
    $this->init();
  }

  private function init()
  {
    add_filter('https_local_ssl_verify', '__return_false');
    add_filter('https_ssl_verify', '__return_false');
    add_filter('block_local_requests', '__return_false');
    $this->base_url = 'http://localhost:8081';
    $this->routes = array(
      'generate_webhook_key' => function ($body) {
        return array(
          'path' => $this->base_url . '/business/generate-webhook-key',
          'method' => 'get',
          'options' => array(
            'headers' => array(
              'Content-type' => 'application/json',
              'x-api-key' => $this->api_key
            )
          )
        );
      },
      'save_webhook_url' => function ($body) {
        return array(
          'path' => $this->base_url . '/business/edit',
          'method' => 'post',
          'options' => array(
            'headers' => array(
              'Content-type' => 'application/json',
              'x-api-key' => $this->api_key
            ),
            'body' => wp_json_encode($body),
            'data_format' => 'body',
          )
        );
      },
      'webhook_handshake' => function ($body) {
        return array(
          'path' => $this->base_url . '/business/webhook-handshake',
          'method' => 'post',
          'options' => array(
            'headers' => array(
              'Content-type' => 'application/json',
              'x-api-key' => $this->api_key
            ),
            'body' => wp_json_encode($body),
            'data_format' => 'body',
          )
        );
      }
    );
  }

  public function dispatch($route, $request_body = null)
  {
    $handler = $this->routes[$route];

    if (empty($handler)) {
      wp_send_json_error('API Error: No Handler', 400);
    }

    $route_data = $handler($request_body);

    if ($route_data['method'] === 'post') {
      $response = wp_remote_post($route_data['path'], $route_data['options']);
    } else {
      $response = wp_remote_get($route_data['path'], $route_data['options']);
    }

    if (is_wp_error($response)) {
      wp_send_json_error('API Error: ' . $response->get_error_message(), $response->get_error_code());
    }
    try {
      $body = json_decode($response['body'], true);
    } catch (Exception $e) {
      $body = json_decode($response, true);
    }

    if (!json_last_error() === JSON_ERROR_NONE) {
      return wp_send_json_error('API JSON ERROR: ' . json_last_error(), 400);
    }

    if (empty($body)) {
      return wp_send_json_error('API Error: Empty ' . $body, 400);
    }

    if (empty($body['data'])) {
      return wp_send_json_error('API Error: ' . $body['message'], 400);
    }

    return $body['data'];
  }
}