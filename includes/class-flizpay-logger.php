<?php

/**
 * FlizPay Logger Utility
 *
 * Handles logging of plugin activities for debugging and monitoring
 * with Log ingestion integration
 *
 * @link       https://www.flizpay.de
 * @since      2.4.2
 *
 * @package    Flizpay
 * @subpackage Flizpay/includes
 */

class Flizpay_Logger
{
    /**
     * The ID for this plugin
     *
     * @since    2.4.2
     * @access   private
     * @var      string    $plugin_name   The ID for this plugin
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    2.4.2
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * The logging level
     *
     * @since    2.4.2
     * @access   private
     * @var      string    $log_level    The logging level (debug, info, warning, error)
     */
    private $log_level;

    /**
     * Whether logging is enabled
     *
     * @since    2.4.2
     * @access   private
     * @var      bool    $enabled    Whether logging is enabled
     */
    private $enabled;

    /**
     * The Log Ingestion Tool source token
     *
     * @since    2.4.2
     * @access   private
     * @var      string    $log_token    The Log Ingestion Tool source token
     */
    private $log_token;

    /**
     * The Log Ingestion Tool API endpoint
     *
     * @since    2.4.2
     * @access   private
     * @var      string    $log_endpoint    The Log Ingestion Tool API endpoint
     */
    private $log_endpoint;

    /**
     * The singleton instance
     *
     * @since    2.4.2
     * @access   public
     * @var      Flizpay_Logger    $instance    The singleton instance
     */
    public static $instance;

    /**
     * The gateway instance
     *
     * @since    2.4.2
     * @access   public
     * @var      WC_Flizpay_Gateway $gateway    The gateway instance
     */
    public static $gateway;

    /**
     * Get the singleton instance
     *
     * @since    2.4.2
     * @return   Flizpay_Logger    The singleton instance
     */
    public static function get_instance($gateway)
    {
        if (isset($gateway)) {
            self::$gateway = $gateway;
        }
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the class and set its properties.
     *
     * @since    2.4.2
     */
    private function __construct()
    {
        $this->plugin_name = 'flizpay';
        $this->version = FLIZPAY_VERSION;
        $this->init_settings();
    }

    /**
     * Initialize logger settings from plugin options
     *
     * @since    2.4.2
     */
    public function init_settings()
    {
        $this->enabled = true; // always enabled
        $this->log_level = self::$gateway->get_option('flizpay_log_level', 'debug');
        $this->log_token = self::$gateway->get_option('flizpay_log_token', '');
        $this->log_endpoint = self::$gateway->get_option('flizpay_log_endpoint', '');
    }

    /**
     * Log a debug message
     *
     * @since    2.4.2
     * @param    string    $message    The message to log
     * @param    array     $context    Additional context data for the log
     */
    public function debug($message, $context = array())
    {
        if ($this->should_log('debug')) {
            $this->log('debug', $message, $context);
        }
    }

    /**
     * Log an info message
     *
     * @since    2.4.2
     * @param    string    $message    The message to log
     * @param    array     $context    Additional context data for the log
     */
    public function info($message, $context = array())
    {
        if ($this->should_log('info')) {
            $this->log('info', $message, $context);
        }
    }

    /**
     * Log a warning message
     *
     * @since    2.4.2
     * @param    string    $message    The message to log
     * @param    array     $context    Additional context data for the log
     */
    public function warning($message, $context = array())
    {
        if ($this->should_log('warning')) {
            $this->log('warning', $message, $context);
        }
    }

    /**
     * Log an error message
     *
     * @since    2.4.2
     * @param    string    $message    The message to log
     * @param    array     $context    Additional context data for the log
     */
    public function error($message, $context = array())
    {
        if ($this->should_log('error')) {
            $this->log('error', $message, $context);
        }
    }

    /**
     * Log an exception
     *
     * @since    2.4.2
     * @param    Exception    $exception    The exception to log
     * @param    string       $message      Additional message to log with the exception
     * @param    array        $context      Additional context data for the log
     */
    public function exception(Exception $exception, $message = '', $context = array())
    {
        if ($this->should_log('error')) {
            $context['exception'] = array(
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            );

            $log_message = empty($message)
                ? sprintf('Exception: %s', $exception->getMessage())
                : sprintf('%s. Exception: %s', $message, $exception->getMessage());

            $this->log('error', $log_message, $context);
        }
    }

    /**
     * Check if a given log level should be logged
     *
     * @since    2.4.2
     * @param    string    $level    The log level to check
     * @return   bool                Whether the log level should be logged
     */
    private function should_log($level)
    {
        if (!$this->enabled) {
            return false;
        }

        $levels = array(
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3
        );

        // If level is not recognized, use the lowest level
        $current_level = isset($levels[$this->log_level]) ? $levels[$this->log_level] : 0;
        $check_level = isset($levels[$level]) ? $levels[$level] : 0;

        return $check_level >= $current_level;
    }

    /**
     * Send a log to Log Ingestion Tool
     *
     * @since    2.4.2
     * @param    string    $level      The log level
     * @param    string    $message    The message to log
     * @param    array     $context    Additional context data for the log
     */
    private function log($level, $message, $context = array())
    {
        if (empty($this->log_token)) {
            // If Log Ingestion Tool token is not set, log to WC_Logger instead
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->log($level, $message, array(
                    'source' => 'flizpay',
                    'context' => $context
                ));
            }
            return;
        }

        // Prepare the data for Log Ingestion Tool
        $data = array(
            'dt' => gmdate('c'),  // ISO 8601 format
            'level' => $level,
            'message' => $message,
            'plugin' => 'FLIZpay for WooCommerce',
            'version' => $this->version,
            'site_url' => home_url(),
        );

        // Add context if provided
        if (!empty($context)) {
            $data = array_merge($data, $context);
        }

        // Send to Log Ingestion Tool
        $this->send_logs($data);
    }

    /**
     * Send log data to Log Ingestion Tool
     *
     * @since    2.4.2
     * @param    array    $data    The log data to send
     */
    private function send_logs($data)
    {
        if (empty($this->log_token)) {
            return;
        }

        try {
            $response = wp_remote_post($this->log_endpoint, array(
                'method' => 'POST',
                'timeout' => 5,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->log_token
                ),
                'body' => wp_json_encode($data),
                'data_format' => 'body',
            ));

            // Handle errors if needed
            if (is_wp_error($response)) {
                if (function_exists('wc_get_logger')) {
                    $logger = wc_get_logger();
                    $logger->error('FLIZpay logging error: ' . $response->get_error_message(), array('source' => 'flizpay'));
                }
            }
        } catch (Exception $e) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error('FLIZpay exception: ' . $e->getMessage(), array('source' => 'flizpay'));
            }
        }
    }
}