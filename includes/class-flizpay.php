<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://www.flizpay.de
 * @since      1.0.0
 *
 * @package    Flizpay
 * @subpackage Flizpay/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Flizpay
 * @subpackage Flizpay/includes
 * @author     Flizpay <carlos.cunha@flizpay.de>
 */
class Flizpay
{

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Flizpay_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		if (defined('FLIZPAY_VERSION')) {
			$this->version = FLIZPAY_VERSION;
		} else {
			$this->version = '1.2.3';
		}
		$this->plugin_name = 'flizpay';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		$this->update_old_payment_failed_page();
	}

	/**
	 * Remove the old payment failed page if it's still present
	 * 
	 * @return void
	 * 
	 * @since 1.2.1
	 */

	public function update_old_payment_failed_page()
	{
		try {
			$page_slug = 'flizpay-payment-fail';
			$page = get_page_by_path($page_slug);
			$page_slug2 = 'flizpay-payment-fail-2';
			$page2 = get_page_by_path($page_slug2);
			$page_slug3 = 'flizpay-payment-fail-3';
			$page3 = get_page_by_path($page_slug3);
			$page_slug4 = 'flizpay-payment-fail-4';
			$page4 = get_page_by_path($page_slug4);

			if ($page || $page2 || $page3 || $page4) {
				Flizpay_Deactivator::deactivate();
				Flizpay_Activator::activate();
			}
		} catch (Exception $e) {

		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Flizpay_Loader. Orchestrates the hooks of the plugin.
	 * - Flizpay_i18n. Defines internationalization functionality.
	 * - Flizpay_Admin. Defines all hooks for the admin area.
	 * - Flizpay_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{

		/**
		 * The class responsible for activating the plugin
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-flizpay-activator.php';

		/**
		 * The class responsible for deactivating the plugin
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-flizpay-deactivator.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-flizpay-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-flizpay-i18n.php';

		/**
		 * The class responsible for defining all api calls to flizpay
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-flizpay-api.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-flizpay-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-flizpay-public.php';

		$this->loader = new Flizpay_Loader();

		/**
		 * The class responsible for defining all actions that occur in payment plugin execution
		 */
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-flizpay-gateway.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Flizpay_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{

		$plugin_i18n = new Flizpay_i18n();

		$this->loader->add_action('init', $plugin_i18n, 'load_plugin_textdomain');

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{

		$plugin_admin = new Flizpay_Admin($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
		$this->loader->add_filter('flizpay_load_settings', $plugin_admin, 'load_form_fields');
		$this->loader->add_filter('plugin_action_links_' . basename(dirname(__DIR__)) . '/flizpay.php', $plugin_admin, 'flizpay_plugin_links');
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks()
	{

		$plugin_public = new Flizpay_Public($this->get_plugin_name(), $this->get_version());

		// Hook the custom function to the 'before woocommerce init' action
		$this->loader->add_action('before_woocommerce_init', $plugin_public, 'declare_cart_checkout_blocks_compatibility');
		// Hook the custom function to the 'woocommerce blocks_loaded' action
		$this->loader->add_action('woocommerce_blocks_loaded', $plugin_public, 'flizpay_reg_order_payment_method_type');

		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
		$this->loader->add_action("wp_ajax_flizpay_get_payment_data", $plugin_public, "flizpay_get_payment_data");
		$this->loader->add_action("wp_ajax_nopriv_flizpay_get_payment_data", $plugin_public, "flizpay_get_payment_data");
		$this->loader->add_action("wp_ajax_flizpay_order_finish", $plugin_public, "flizpay_order_finish");
		$this->loader->add_action("wp_ajax_nopriv_flizpay_order_finish", $plugin_public, "flizpay_order_finish");

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run()
	{
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Flizpay_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader()
	{
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version()
	{
		return $this->version;
	}



}
