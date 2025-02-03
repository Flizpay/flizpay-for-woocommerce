<?php
/**
 * Centralized Singleton for communication with all FLIZpay services via API 
 * Check our documentation at https://docs.flizpay.de
 * 
 * @since 1.0.0
 */
class WC_Flizpay_API
{
  private $api_key;
  private $base_url;
  private $routes;

  public static $instance;

  /**
   * Obtain the current instance of the API class for a given API KEY
   * 
   * @param string $api_key
   * @return WC_Flizpay_API
   * 
   * @since 1.0.0
   */
  public static function get_instance($api_key)
  {
    if (!isset(self::$instance) || self::$instance->api_key !== $api_key) {
      self::$instance = new WC_Flizpay_API($api_key);
    }
    return self::$instance;
  }

  /**
   * Private constructor called by get_instance
   * Sets the API key and initialize the API routes
   * 
   * @param string $api_key
   * 
   * @since 1.0.0
   */
  private function __construct($api_key)
  {
    $this->api_key = $api_key;
    $this->init();
  }

  /**
   * Initialize the API Routes and base URL for further usage
   * 
   * @return void
   * 
   * @since 1.0.0
   */
  private function init()
  {
    $this->base_url = 'http://localhost:8080';
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
      'create_transaction' => function ($body) {
        return array(
          'path' => $this->base_url . '/transactions',
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
    );
  }

  /**
   * Performs an API call to the specified route, with the given body. 
   * When $api_mode is set false, this function will not immediately return success or error
   * with wp_send_json_error or wp_send_json_success, instead it will return all responses to the caller.
   * 
   * @param string $route
   * @param array $request_body
   * @param bool $api_mode
   * @return void | array
   * 
   * @since 1.0.0
   */
  public function dispatch($route, $request_body = null, $api_mode = true)
  {
    $handler = $this->routes[$route];

    if (empty($handler) && $api_mode) {
      wp_send_json_error('API Error: No Handler', 400);
    }

    $route_data = $handler($request_body);

    if ($route_data['method'] === 'post') {
      $response = wp_remote_post($route_data['path'], $route_data['options']);
    } else {
      $response = wp_remote_get($route_data['path'], $route_data['options']);
    }

    if ($response && is_wp_error($response) && $api_mode) {
      wp_send_json_error('API Error: ' . $response->get_error_message(), $response->get_error_code());
    }
    try {
      if (is_array($response)) {
        $body = json_decode($response['body'], true);
      } else {
        $body = null;
      }
    } catch (Exception $e) {
      $body = json_decode($response, true);
    }

    if (!json_last_error() === JSON_ERROR_NONE && $api_mode) {
      return wp_send_json_error('API JSON ERROR: ' . json_last_error(), 400);
    }

    if (empty($body) && $api_mode) {
      return wp_send_json_error('API Error: Empty ' . $body, 400);
    }

    if (empty($body['data']) && $api_mode) {
      return wp_send_json_error('API Error: ' . $body['message'], 400);
    }

    return $body['data'] ?? $body;
  }
}