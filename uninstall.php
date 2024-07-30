<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future versions of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://www.flizpay.de
 * @since      1.0.0
 *
 * @package    Flizpay
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// ** Clean up options **
delete_option('flizpay_api_key');
delete_option('flizpay_webhook_key');
delete_option('flizpay_webhook_url');
delete_option('flizpay_enabled');
delete_option('flizpay_webhook_alive');
delete_option('woocommerce_flizpay_settings');

// ** For multisite: Delete options across all sites **
if (is_multisite()) {
	global $wpdb;
	$blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
	foreach ($blog_ids as $blog_id) {
		switch_to_blog($blog_id);
		delete_option('flizpay_api_key');
		delete_option('flizpay_webhook_key');
		delete_option('flizpay_webhook_url');
		delete_option('flizpay_enabled');
		delete_option('flizpay_webhook_alive');
		delete_option('woocommerce_flizpay_settings');
		restore_current_blog();
	}
}

// ** Clean up custom tables **
global $wpdb;
$table_name = $wpdb->prefix . 'flizpay_custom_table';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// ** Clean up custom post types **
$custom_post_types = array('flizpay_custom_post_type');
foreach ($custom_post_types as $post_type) {
	$posts = get_posts(
		array(
			'post_title' => wp_strip_all_tags('Flizpay Payment Fail'),
		)
	);

	foreach ($posts as $post) {
		wp_delete_post($post->ID, true);
	}
}

// ** Clean up transients **
delete_transient('flizpay_transient');