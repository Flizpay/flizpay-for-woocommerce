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
            'meta_input' => array(
                '_flizpay_system_page' => true // Custom meta key to identify the page
            ),
        );

        // Insert the post into the database
        wp_insert_post($flizpay_fail);

        add_filter('wp_get_nav_menu_items', 'flizpay_exclude_menu_items', 10, 3);
        add_action('pre_get_posts', 'flizpay_exclude_pages_from_search');

    }

    function flizpay_exclude_menu_items($items, $menu, $args)
    {
        foreach ($items as $key => $item) {
            if (get_post_meta($item->object_id, '_flizpay_system_page', true)) {
                unset($items[$key]);
            }
        }
        return $items;
    }

    function flizpay_exclude_pages_from_search($query)
    {
        if ($query->is_search && !is_admin()) {
            $meta_query = array(
                array(
                    'key' => '_flizpay_system_page',
                    'compare' => 'NOT EXISTS',
                ),
            );
            $query->set('meta_query', $meta_query);
        }
        return $query;
    }
}
