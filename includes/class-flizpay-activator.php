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
 * @author     Flizpay <carlos.cunha@flizpay.de>
 */
class Flizpay_Activator
{

    /**
     * Activate FLIZpay by adding the custom payment failure page. (use period)
     *
     * Adds a post page to be used when payment fails and register a filter and an action
     * to exclude it from the navigation menu and search results.
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

    /** 
     * Filter for excluding the payment failed page from the navigation menu
     * 
     * @return array
     * 
     * @since 1.0.0
     */
    function flizpay_exclude_menu_items($items, $menu, $args)
    {
        foreach ($items as $key => $item) {
            if (get_post_meta($item->object_id, '_flizpay_system_page', true)) {
                unset($items[$key]);
            }
        }
        return $items;
    }

    /**
     * Action for excluding the payment failed page from search results
     * @param mixed $query
     * @return mixed
     * 
     * @since 1.0.0
     */
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
