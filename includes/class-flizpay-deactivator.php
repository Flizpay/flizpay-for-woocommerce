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
