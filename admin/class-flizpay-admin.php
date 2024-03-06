<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.flizpay.de
 * @since      1.0.0
 *
 * @package    Flizpay
 * @subpackage Flizpay/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Flizpay
 * @subpackage Flizpay/admin
 * @author     Flizpay <roberto.ammirata@flizpay.de>
 */
class Flizpay_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Flizpay_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Flizpay_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/flizpay-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Flizpay_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Flizpay_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/flizpay-admin.js', array( 'jquery' ), $this->version, false );

	}

    /**
     * @param $links
     * @return array|string[]
     */
    public function flizpay_plugin_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=flizpay' ) . '">' . __( 'Settings', 'flizpay-gateway' ) . '</a>'
        );

        return array_merge( $plugin_links, $links );
    }

    /**
     * @return array
     */
    public function load_form_fields() {
        return array(
			'description_banner' => array(
                'title' => __('', 'flizpay'), // Empty title, used for HTML output
                'type' => 'title', // Using 'title' as a workaround to insert HTML
                'description' => $this->add_description_banner(), // Including HTML content via a method
            ),
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable Flizpay Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'Flizpay',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => 'Pay with Flizpay payment gateway.',
                'desc_tip'    => true,
            ),
            'flizpay_client_id' => array(
                'title'       => 'Client ID',
                'label'       => 'Enter Client ID',
                'type'        => 'text',
                'description' => 'Enter you client ID.',
                'desc_tip'    => true,
			),
			'flizpay_shop_iban' => array(
				'title'       => 'IBAN',
				'type'        => 'text',
				'description' => 'Enter your shop IBAN for receiving payments.',
				'desc_tip'    => true,
			)
        );
    }
	
	private function add_description_banner() {
		// Directly return HTML content as a string
		return '
		<div style="background-color: #f7f7f7; margin-bottom: 20px; padding: 20px; border-left: 4px solid #007cba;">
			<h2>Welcome to the Flizpay Gateway for Woocommerce plugin!</h2>
			<p>To start accepting payments from your customers at free rates, you\'ll need to follow three simple steps:</p>
			<ol>
				<li><a href="https://www.flizpay.de" target="_blank">Sign up for Fliz Business</a> if you don\'t have an account already.</li>
				<li>Once your Fliz Business has been approved get your clientId and paste it in the field below.</li>
				<li>Place your shop IBAN in the field below to start receiving payments.</li>
			</ol>
		</div>
		';
	}
}
