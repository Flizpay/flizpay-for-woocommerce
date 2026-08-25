<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

require_once('includes/class-flizpay-api.php');
require_once('includes/class-flizpay-pairing.php');

$flizpay_settings = get_option('woocommerce_flizpay_settings');
$api_key = is_array($flizpay_settings) ? ($flizpay_settings['flizpay_api_key'] ?? '') : '';
$managed_disconnect_failed = false;

if ($api_key) {
	if (!empty($flizpay_settings['flizpay_managed_connection'])) {
		$managed_disconnect_failed = !Flizpay_Pairing::disconnect_managed_connection($flizpay_settings);
	} else {
		$api_client = WC_Flizpay_API::get_instance($api_key);

		$api_client->dispatch('edit_business', array("isActive" => false), false);
		$api_client->dispatch('edit_business', array("webhookUrl" => ''), false);
	}
}

// Clean up options
$options = array(
	'flizpay_api_key',
	'flizpay_managed_connection',
	'flizpay_webhook_key',
	'flizpay_webhook_url',
	'flizpay_enabled',
	'flizpay_webhook_alive',
	'flizpay_plugin_version_sync_needed',
	'flizpay_reported_plugin_version',
	'woocommerce_flizpay_settings',
);
$managed_connection_credential_options = array(
	'flizpay_api_key',
	'flizpay_managed_connection',
	'flizpay_webhook_key',
	'flizpay_webhook_url',
	'woocommerce_flizpay_settings',
);

foreach ($options as $option) {
	if ($managed_disconnect_failed && in_array($option, $managed_connection_credential_options, true)) {
		continue;
	}
	delete_option($option);
}

// For multisite: Delete options across all sites
if (is_multisite()) {
	$blog_ids = get_sites(array('fields' => 'ids'));
	foreach ($blog_ids as $blog_id) {
		switch_to_blog($blog_id);
		foreach ($options as $option) {
			if ($managed_disconnect_failed && in_array($option, $managed_connection_credential_options, true)) {
				continue;
			}
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
delete_transient('flizpay_plugin_version_sync_failed');
