<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.flizpay.de
 * @since      1.0.0
 *
 * @package    Flizpay
 * @subpackage Flizpay/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Flizpay
 * @subpackage Flizpay/includes
 * @author     Flizpay <carlos.cunha@flizpay.de>
 */
class Flizpay_Deactivator
{

	/**
	 * Delete the FLIZpay payment failure page
	 *
	 * @since    1.0.0
	 */
	public static function deactivate()
	{
		$page_slug = 'flizpay-payment-fail';
		$page = get_page_by_path($page_slug);
		if ($page) {
			wp_delete_post($page->ID, true);
		}

		$page_slug2 = 'flizpay-payment-fail-2';
		$page2 = get_page_by_path($page_slug2);
		if ($page2) {
			wp_delete_post($page2->ID, true);
		}

		$page_slug3 = 'flizpay-payment-fail-3';
		$page3 = get_page_by_path($page_slug3);
		if ($page3) {
			wp_delete_post($page3->ID, true);
		}

		$page_slug4 = 'flizpay-payment-fail-4';
		$page4 = get_page_by_path($page_slug4);
		if ($page4) {
			wp_delete_post($page4->ID, true);
		}

		$payment_failed_slug = 'payment-failed';
		$payment_failed_page = get_page_by_path($payment_failed_slug);
		if ($payment_failed_page) {
			wp_delete_post($payment_failed_page->ID, true);
		}

		require_once('class-flizpay-api.php');

		$flizpay_settings = get_option('woocommerce_flizpay_settings');

		if (!$flizpay_settings)
			return;

		$api_key = $flizpay_settings['flizpay_api_key'];

		if (!$api_key)
			return;

		$api_client = WC_Flizpay_API::get_instance($api_key);

		$api_client->dispatch('edit_business', array("isActive" => false), false);
	}

}
