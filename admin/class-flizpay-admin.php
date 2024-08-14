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
 * @author     Flizpay <carlos.cunha@flizpay.de>
 */
class Flizpay_Admin
{

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
	public function __construct($plugin_name, $version)
	{

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

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

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/flizpay-admin.css', array(), $this->version, 'all');

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{

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

		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/flizpay-admin.js', array('jquery'), $this->version, false);
		wp_localize_script($this->plugin_name, 'flizpayParams', array('nonce' => wp_create_nonce('test_connection_nonce')));

	}

	/**
	 * @param $links
	 * @return array|string[]
	 */
	public function flizpay_plugin_links($links)
	{
		$plugin_links = array(
			'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=flizpay') . '">' . __('Settings', 'flizpay-gateway') . '</a>',
			'<a href="https://www.docs.flizpay.de/docs/intro/">' . __('Docs', 'flizpay-gateway') . '</a>'
		);

		return array_merge($plugin_links, $links);
	}

	/**
	 * @return array
	 */
	public function load_form_fields()
	{
		return array(
			'description_banner' => array(
				'title' => '', // Empty title, used for HTML output
				'type' => 'title', // Using 'title' as a workaround to insert HTML
				'description' => $this->add_description_banner(), // Including HTML content via a method
			),
			'flizpay_enabled' => array(
				'title' => 'Aktiviert<br><p style="font-style: italic;">Enabled</p>',
				'label' => 'FLIZpay aktiviert<br><p style="font-style: italic;">FLIZpay enabled</p>',
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no',
			),
			'flizpay_api_key' => array(
				'title' => 'API KEY',
				'label' => 'Enter API KEY',
				'type' => 'password',
				'description' => '<span style="color: black">Gib deinen API KEY ein.  Der API KEY ist ein sensibler Datensatz und sollte wie ein Passwort behandelt werden.</span><br>
				<p style="font-style: italic; color: #646970;">Enter you API KEY. This information is very sensitive, it should be treated as a password.</p>',
				'desc_tip' => false,
			),
			'flizpay_webhook_url' => array(
				'title' => '',
				'type' => 'text',
				'description' => '',
				'default' => '',
				'desc_tip' => false,
			),
			'flizpay_webhook_alive' => array(
				'title' => 'Verbindung hergestellt.<br><p style="font-style: italic;">Connection Established</p>',
				'type' => 'checkbox',
				'label' => '<div id="connection-stablished-description">Dies zeigt an, wann unsere Server über die Webhook-URL mit Ihrer Site kommunizieren können. Laden Sie die Seite einige Sekunden nach dem Testen der Verbindung neu.<br>
				<p style="font-style: italic;">This indicates when our servers manage to communicate with your site via the webhook URL. Reload the page a few seconds after testing the connection.</p></div>',
				'default' => 'no',
				'desc_tip' => false,
			),
		);
	}

	private function add_description_banner()
	{
		return <<<HTML
			<div class='flizpay-description-banner'>
				<div class="flizpay-german-banner">
					<div style="flizpay-banner-header">
						<p style='font-style: italic; margin-top: 10px;'>English version below</p>
						<p class="flizpay-header-text">Willkommen bei FLIZpay in WooCommerce!</p>
					</div>
					<p>Anleitung:</p>
					<ol>
						<li>Wenn du noch kein FLIZ-Firmenkonto hast, klicke bitte <a href='https://app.flizpay.de' target='_blank'>hier</a> und registriere dich jetzt.</li>
						<li>Nachdem du dich registriert hast, generiere bitte einen neuen API Key in deinem FLIZ-Firmenkonto und füge ihn in das unten stehende Feld “API KEY” ein. Du findest den API-Schlüssel im Abschnitt “Installation” deines FLIZ-Firmenkontos.</li>
						<li>Klicke anschließend auf den Button “Änderungen speichern”.</li>
						<li>Warte etwa 5 Sekunden, bis die Seite automatisch neu lädt.</li>
						<li>Nach dem Neuladen der Seite erscheint unten eine grüne Box, die die erfolgreiche Konfiguration des FLIZpay-Plugins anzeigt. Du bist jetzt bereit, gebührenfreie Zahlungen zu empfangen.</li>
						<li>Stelle sicher, dass du mit FLIZ so viele Gebühren wie möglich sparst. Vergiss also nicht, FLIZpay in der für Kunden zur Auswahl stehenden Liste an Zahlungsmethoden an die erste Stelle zu setzen. Klicke im linken Menü auf “WooCommerce”, dann im Untermenü auf “Einstellungen”. Klicke anschließend im Menü oben auf den Tab “Zahlungen”. Auf dieser Seite kannst du per Drag-and-Drop oder mit den Pfeilen die Position von FLIZpay in der Liste der verfügbaren Zahlungsmethoden anpassen. Klicke zum Schluss unten links auf “Änderungen speichern”.</li>
						<li>Darüber hinaus empfehlen wir dir, Cashback für deine Kunden zu aktivieren. Ändere das Zahlungsverhalten deiner Kunden, damit sie FLIZ nutzen - denn FLIZ ist gebührenfrei. Um das zu erreichen, kannst du einen prozentualen Cashback festlegen, der als Rabatt von jedem Einkauf abgezogen wird, den deine Kunden mit FLIZ tätigen. Je mehr Cashback du gibst, desto höher wird der Anteil von FLIZ an deiner Kasse, was bedeutet, dass deine Kundenbasis zu einer gebührenfreien Zahlungsmethode gewechselt ist. Du kannst Cashback in deinem FLIZ-Firmenkonto aktivieren, <a href='https://app.flizpay.de' target='_blank'>klicke hier.</a></li>
					</ol>
					
					<p>
						Wenn du mehr darüber erfahren möchtest, wie du dieses Plugin für deine Bedürfnisse konfigurieren kannst, <a 
							href="https://www.docs.flizpay.de/docs/intro"
							target="_blank"
							>schau dir unsere Dokumentation an.</a>
					</p>
				</div>
				<div class="flizpay-english-banner">
					<div style="flizpay-banner-header">
						<p class="flizpay-header-text">Welcome to FLIZpay in WooCommerce!</p>
					</div>
					<p>Instructions:</p>
					<ol>
						<li>If you haven’t signed up for a FLIZ company account, please <a href='https://app.flizpay.de' target='_blank'>click here</a> and sign up.</li>
						<li>Once you have signed up, please generate a new API Key in your FLIZ company account and paste it in the “API KEY” field below. You will find the API KEY in the “Installation” section of your company account.</li>
						<li>Afterwards, click the button “Save changes”.</li>
						<li>Wait about 5 seconds and the page will reload automatically.</li>
						<li>After the page reloaded, a green box will appear at the bottom, indicating the successful configuration of the FLIZpay plugin. You are now ready to receive fee-free payments.</li>
						<li>Make sure you save as many fees as possible with fee-free FLIZ. Don’t forget to put FLIZpay first in the list of payment methods presented to customers. To do this go to the left menu and click on “WooCommerce”, then click on the sub-menu “Settings”. Now click on the tab “Payments” in the menu at the top of the page. Here, you can drag and drop or use the arrows to adjust the position of FLIZpay in the list of available payment methods. When you are finished, go to the bottom left of that page and click “Save changes”.</li>
						<li>Moreover, we advise you to activate cashback for your customers. With cashback, you change your customers’ payment behavior to ensure they use fee-free payment method FLIZ. This way, customers support your bottom line and optimize their own finances at the same time. You can activate cashback in your FLIZ company account, <a href='https://app.flizpay.de' target='_blank'>click here.</a></li>
					</ol>
					
					<p>
						If you'd like to know more about how to configure this plugin for your needs, <a 
							href="https://www.docs.flizpay.de/docs/intro"
							target="_blank"
							>check out our documentation.</a>
					</p>
				</div>
			</div>
		HTML;
	}
}
