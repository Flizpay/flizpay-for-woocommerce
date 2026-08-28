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
		if (function_exists('as_unschedule_all_actions')) {
			as_unschedule_all_actions('flizpay_reconciliation_scan', array(), 'flizpay');
		}

		require_once('class-flizpay-api.php');
		require_once('class-flizpay-pairing.php');

		$flizpay_settings = get_option('woocommerce_flizpay_settings');

		if (!$flizpay_settings)
			return;

		$api_key = $flizpay_settings['flizpay_api_key'] ?? '';

		if (!$api_key)
			return;

		if (!empty($flizpay_settings['flizpay_managed_connection'])) {
			// Keep the credentials when FLIZpay could not be reached, so a later uninstall
			// can still release the connection. Once it is released they are dead weight:
			// leaving them behind makes a reactivated plugin look connected while every
			// checkout fails against a key the backend has deleted.
			if (Flizpay_Pairing::disconnect_managed_connection($flizpay_settings)) {
				unset(
					$flizpay_settings['flizpay_api_key'],
					$flizpay_settings['flizpay_webhook_key'],
					$flizpay_settings['flizpay_managed_connection'],
					$flizpay_settings['flizpay_api_base_url']
				);
				$flizpay_settings['flizpay_webhook_alive'] = 'no';
				update_option('woocommerce_flizpay_settings', $flizpay_settings, false);
			}
			return;
		}

		$api_client = WC_Flizpay_API::get_instance($api_key);

		$api_client->dispatch('edit_business', array("isActive" => false));
	}

}
