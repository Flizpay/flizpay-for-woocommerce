<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.flizpay.de
 * @since      1.0.0
 *
 * @package    Flizpay
 * @subpackage Flizpay/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Flizpay
 * @subpackage Flizpay/includes
 * @author     Flizpay <roberto.ammirata@flizpay.de>
 */
class Flizpay_Activator
{

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        // Create post object
        $flizpay_fail = array(
            'post_title' => wp_strip_all_tags('Flizpay Payment Fail'),
            'post_content' => 'Your order has been cancelled due to unsuccessfull payment from Flizpay.',
            'post_status' => 'publish',
            'post_author' => 1,
            'post_type' => 'page',
        );

        // Insert the post into the database
        wp_insert_post($flizpay_fail);
    }

}
