<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

require_once('includes/class-flizpay-api.php');

$flizpay_settings = get_option('woocommerce_flizpay_settings');
error_log(json_encode($flizpay_settings));
$api_key = $flizpay_settings['flizpay_api_key'];

$api_client = WC_Flizpay_API::get_instance($api_key);

$api_client->dispatch('save_webhook_url', array("webhookUrl" => ''), false);

// Clean up options
$options = array(
	'flizpay_api_key',
	'flizpay_webhook_key',
	'flizpay_webhook_url',
	'flizpay_enabled',
	'flizpay_webhook_alive',
	'woocommerce_flizpay_settings',
);

foreach ($options as $option) {
	delete_option($option);
}

// For multisite: Delete options across all sites
if (is_multisite()) {
	$blog_ids = get_sites(array('fields' => 'ids'));
	foreach ($blog_ids as $blog_id) {
		switch_to_blog($blog_id);
		foreach ($options as $option) {
			delete_option($option);
		}
		restore_current_blog();
	}
}

// Clean up custom post types
$custom_post_types = array('flizpay_custom_post_type');
foreach ($custom_post_types as $post_type) {
	$posts = get_posts(array(
		'post_type' => $post_type,
		'post_status' => 'any',
		'numberposts' => -1
	));

	foreach ($posts as $post) {
		wp_delete_post($post->ID, true);
	}
}

// Clean up transients
delete_transient('flizpay_transient');
