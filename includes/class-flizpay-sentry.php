<?php
/**
 * FLIZpay Sentry Helper Class
 *
 * Handles all Sentry error tracking functionality with proper checks
 * for PHP version and Sentry availability.
 *
 * @package Flizpay
 * @since 2.4.11
 */

if (!defined('ABSPATH')) {
    exit;
}

class Flizpay_Sentry {
    
    /**
     * Minimum PHP version required for Sentry
     */
    const MIN_PHP_VERSION = '8.2.0';
    
    /**
     * Check if Sentry can be initialized
     *
     * @return bool
     */
    public static function can_initialize() {
        return version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
    }
    
    /**
     * Initialize Sentry if requirements are met
     */
    public static function init() {
        // Skip if PHP version is too low
        if (!self::can_initialize()) {
            return;
        }
        
        // Skip if autoloader wasn't loaded
        if (!function_exists('\Sentry\init')) {
            return;
        }
        
        // Check for user consent
        $flizpay_settings = get_option('woocommerce_flizpay_settings', []);
        
        if (!isset($flizpay_settings['flizpay_sentry_enabled'])) {
            $flizpay_settings['flizpay_sentry_enabled'] = 'yes';
            update_option('woocommerce_flizpay_settings', $flizpay_settings);
        }
        
        // Initialize Sentry
        \Sentry\init([
            'dsn' => 'https://d2941234a076cdd12190f707115ca5c9@o4507078336053248.ingest.de.sentry.io/4509638952419408',
            'traces_sample_rate' => 1,
            'before_send' => [__CLASS__, 'before_send_callback']
        ]);
    }
    
    /**
     * Sentry before_send callback
     *
     * @param \Sentry\Event $event
     * @return \Sentry\Event|null
     */
    public static function before_send_callback(\Sentry\Event $event) {
        // Check if error reporting is enabled
        $flizpay_settings = get_option('woocommerce_flizpay_settings', []);
        $disabled = ($flizpay_settings['flizpay_sentry_enabled'] ?? '') !== 'yes';
        if ($disabled) {
            return null;
        }
        
        // Check per-event ignore flag
        $should_ignore_event = ($event->getExtra()['ignore_for_sentry'] ?? 'false') === 'true';
        if ($should_ignore_event) {
            return null;
        }
        
        // Only send errors from FLIZpay plugin
        $pluginPath = plugin_dir_path(dirname(__FILE__));
        
        // Check exceptions
        foreach ($event->getExceptions() ?? [] as $exc) {
            if (!$stack = $exc->getStacktrace()) {
                continue;
            }
            foreach ($stack->getFrames() as $frame) {
                if (($file = $frame->getFile()) && strpos($file, $pluginPath) === 0) {
                    return $event;
                }
            }
        }
        
        // Check message events
        if ($stack = $event->getStacktrace()) {
            foreach ($stack->getFrames() as $frame) {
                if (($file = $frame->getFile()) && strpos($file, $pluginPath) === 0) {
                    return $event;
                }
            }
        }
        
        // No frames under our plugin dir
        return null;
    }
    
    /**
     * Safe wrapper for Sentry\withScope
     *
     * @param callable $callback
     * @return mixed|null
     */
    public static function with_scope($callback) {
        if (function_exists('\Sentry\withScope')) {
            return \Sentry\withScope($callback);
        }
        return null;
    }
    
    /**
     * Safe wrapper for Sentry\captureException
     *
     * @param \Throwable $exception
     * @return \Sentry\EventId|null
     */
    public static function capture_exception($exception) {
        if (function_exists('\Sentry\captureException')) {
            return \Sentry\captureException($exception);
        }
        return null;
    }
}