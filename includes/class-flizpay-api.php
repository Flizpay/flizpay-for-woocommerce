<?php

declare(strict_types=1);

/**
 * Centralized Singleton for communication with all FLIZpay services via API
 * Check our documentation at https://docs.flizpay.de
 *
 * @since 1.0.0
 */
class WC_Flizpay_API
{
    private string $api_key;
    private string $base_url;
    private array $routes;

    public static WC_Flizpay_API $instance;

    /**
     * Obtain the current instance of the API class for a given API KEY
     *
     * @param string $api_key
     * @return WC_Flizpay_API
     *
     * @since 1.0.0
     */
    public static function get_instance($api_key): WC_Flizpay_API
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
    private function init(): void
    {
        $this->base_url = 'https://api.flizpay.de';
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
            'edit_business' => function ($body) {
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
            'get_transaction_status' => function ($body) {
                return array(
                    'path' => $this->base_url . '/transactions/' . rawurlencode((string) ($body['reference'] ?? '')) . '/status',
                    'method' => 'get',
                    'options' => array(
                        'headers' => array(
                            'Content-type' => 'application/json',
                        )
                    )
                );
            },
            'fetch_cashback_data' => function ($body) {
                return array(
                    'path' => $this->base_url . '/business/cashback',
                    'method' => 'get',
                    'options' => array(
                        'headers' => array(
                            'Content-type' => 'application/json',
                            'x-api-key' => $this->api_key
                        )
                    )
                );
            }
        );
    }

    /**
     * Perform a configured FLIZpay request and decode its JSON response.
     *
     * Successful payloads are unwrapped from their optional data envelope. Returned
     * arrays include the internal `_http_status` field so services can classify HTTP
     * failures. Invalid JSON is represented by `_json_error`; transport and unknown
     * route failures are returned as WP_Error instances.
     *
     * @param string $route Name of a route registered in init().
     * @param array<string, mixed>|null $request_body Values consumed by the route and request body.
     * @param array<string, string> $request_headers Additional headers merged over route defaults.
     * @return array<string, mixed>|\WP_Error|null Decoded data with transport metadata, an error, or null.
     *
     * @since 1.0.0
     */
    public function dispatch(
        string $route,
        ?array $request_body = null,
        array $request_headers = array()
    ) {
        if (!isset($this->routes[$route])) {
            return new \WP_Error('flizpay_api_no_handler', 'API Error: No Handler');
        }

        $handler = $this->routes[$route];
        $route_data = $handler($request_body);

        $route_data['options']['headers'] = array_merge(
            $route_data['options']['headers'] ?? array(),
            $request_headers
        );

        if (defined('FLIZPAY_VERSION')) {
            $route_data['options']['headers']['X-FLIZpay-Plugin-Version'] = FLIZPAY_VERSION;
        }

        if ($route_data['method'] === 'post') {
            $response = wp_remote_post($route_data['path'], $route_data['options']);
        } else {
            $response = wp_remote_get($route_data['path'], $route_data['options']);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        try {
            if (is_array($response)) {
                $body = json_decode($response['body'], true);
            } else {
                $body = null;
            }
        } catch (Exception $e) {
            $body = json_decode((string) $response, true);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                '_http_status' => wp_remote_retrieve_response_code($response),
                '_json_error' => true,
            );
        }

        $data = $body['data'] ?? $body;

        if (is_array($data)) {
            $data['_http_status'] = wp_remote_retrieve_response_code($response);
        }

        return is_array($data) ? $data : null;
    }
}
