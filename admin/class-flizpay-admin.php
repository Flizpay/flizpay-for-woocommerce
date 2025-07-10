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
 * Defines the plugin name, version, hooks for
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
	 * The URL of the plugin assets
	 * @since 1.0.0
	 * @access public
	 * @var string $assets_url
	 */
	public $assets_url;

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
		$this->assets_url = plugins_url() . '/' . basename(dirname(__DIR__)) . '/assets/images';

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{

		wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/flizpay-admin.css', array(), $this->version, 'all');

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script('select2');
		wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/flizpay-admin.js', array('jquery', 'select2'), $this->version, false);
		wp_localize_script($this->plugin_name, 'flizpayParams', array(
			'nonce' => wp_create_nonce('test_connection_nonce'),
			'loading_icon' => "$this->assets_url/loading.svg",
			'example_image' => $this->is_english() ? "$this->assets_url/flizpay-checkout-example-en.png" : "$this->assets_url/flizpay-checkout-example-de.png",
			'wp_locale' => get_user_locale() ?? get_locale(),
			'express_checkout_button_dark' => $this->is_english() ? "$this->assets_url/fliz-express-checkout-example-dark-en.png" : "$this->assets_url/fliz-express-checkout-example-dark-de.png",
			'express_checkout_button_light' => $this->is_english() ? "$this->assets_url/fliz-express-checkout-example-light-en.png" : "$this->assets_url/fliz-express-checkout-example-light-de.png",
		));

	}

	/**
	 * Define the admin page links on the plugin table row
	 * 
	 * @param $links
	 * @return array|string[]
	 * 
	 * @since 1.0.0
	 */
	public function flizpay_plugin_links($links)
	{
		$plugin_links = array(
			'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=flizpay') . '">' . __('Settings', 'flizpay-for-woocommerce') . '</a>',
			'<a href="https://www.docs.flizpay.de/docs/intro/">' . __('Docs', 'flizpay-for-woocommerce') . '</a>'
		);

		return array_merge($plugin_links, $links);
	}

	public function is_english()
	{
		return str_contains(get_user_locale() ?? get_locale(), 'en');
	}

	/**
	 * Define the admin fields present in the plugin settings page
	 * 
	 * @return array
	 * 
	 * @since 1.0.0
	 */
	public function load_form_fields()
	{
		return array(
			'description_banner' => array(
				'title' => '', // Empty title, used for HTML output
				'type' => 'title', // Using 'title' as a workaround to insert HTML
				'description' => $this->flizpay_description_banner(), // Including HTML content via a method
			),
			'flizpay_enabled' => array(
				'title' => $this->is_english() ? 'Enabled' : 'Aktiviert',
				'label' => $this->is_english() ? 'FLIZpay enabled' : 'FLIZpay aktiviert',
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no',
			),
			'flizpay_api_key' => array(
				'title' => 'API KEY',
				'label' => 'Enter API KEY',
				'type' => 'password',
				'description' => $this->is_english()
					? 'Enter you API KEY. This information is very sensitive, it should be treated as a password.'
					: 'Gib deinen API KEY ein.  Der API KEY ist ein sensibler Datensatz und sollte wie ein Passwort behandelt werden',
				'desc_tip' => false,
			),
			'flizpay_webhook_alive' => array(
				'title' => $this->is_english() ? 'Connection Established' : 'Verbindung hergestellt',
				'type' => 'checkbox',
				'label' => $this->is_english()
					? '<div id="connection-stablished-description">Note for staging environments that are not public or under strict password protection: You need to either make them public and remove the password protection or allow the domain flizpay.de to bypass these settings. We need to communicate directly with your website.</div>'
					: '<div id="connection-stablished-description">Hinweis für Staging-Umgebungen, die nicht öffentlich sind oder unter strengem Passwortschutz stehen: Du musst entweder die Umgebung öffentlich machen und den Passwortschutz entfernen oder der Domain flizpay.de erlauben, diese Einstellungen zu umgehen. Wir müssen direkt mit deiner Website kommunizieren.</div>',
				'default' => 'no',
				'desc_tip' => false,
			),
			'flizpay_webhook_url' => array(
				'title' => '',
				'type' => 'text',
				'description' => '',
				'default' => '',
				'desc_tip' => false,
			),
			'flizpay_display_logo' => array(
				'title' => 'Logo',
				'label' => $this->is_english()
					? 'Show FLIZpay logo in checkout'
					: 'FLIZpay Logo im Checkout anzeigen',
				'type' => 'checkbox',
				'description' => '',
				'default' => 'yes',
			),
			'flizpay_display_headline' => array(
				'title' => $this->is_english() ? 'Title' : 'Titel',
				'label' => $this->is_english() ? 'Show description in title' : 'Beschreibung im Titel anzeigen',
				'type' => 'checkbox',
				'description' => $this->is_english()
					? '<div id="displayHeadline"><p style="font-style: italic; color: red;">The option to hide the description in the title has been selected. When discount is enabled, '
					. 'the information about discount, such as "FLIZpay – 5% Discount", is therefore missing"</p></div>'
					: '<div id="displayHeadline"><p style="font-style: italic; color: red;">Es wurde ausgewählt, die Beschreibung im Titel nicht anzuzeigen. Wenn Rabatt aktiviert ist, fehlt dadurch die Information zum Rabatt, '
					. 'wie z.B. „FLIZpay – 5 % Rabatt“</p></div>',
				'default' => 'yes',
			),
			'flizpay_display_description' => array(
				'title' => $this->is_english() ? 'Subtitle' : 'Untertitel',
				'label' => $this->is_english() ? 'Show description in subtitle when FLIZpay is selected' : 'Untertitel anzeigen, wenn FLIZpay ausgewählt ist',
				'type' => 'checkbox',
				'description' => '',
				'default' => 'yes',
			),
			'flizpay_order_status' => array(
				'title' => $this->is_english() ? 'Pending Orders' : 'Ausstehende Zahlungen',
				'type' => 'select',
				'description' => $this->is_english()
					? 'If someone doesn’t complete an order using FLIZpay, the order will appear in your admin system as “Payment Pending.” If you want such orders to be shown, select “Pending”. If you don’t want them to be displayed, select “Draft”'
					: 'Falls jemand eine Bestellung mit FLIZpay nicht abgeschlossen hat, wird diese Bestellung in deinem Admin-System als „Zahlung ausstehend” angezeigt. Falls das gewünscht ist, wähle „Ausstehend”. Falls solche Bestellungen nicht angezeigt werden sollen, wähle „Entwurf”.',
				'default' => 'wc-pending',
				'options' => array(
					'wc-pending' => $this->is_english() ? 'Pending' : 'Ausstehend',
					'wc-checkout-draft' => $this->is_english() ? 'Draft' : 'Entwurf',
				),
				'desc_tip' => true,
			),
			'flizpay_enable_express_checkout' => array(
				'title' => $this->is_english() ? 'Express checkout enabled' : 'Express-Checkout Aktiviert',
				'type' => 'checkbox',
				'default' => 'yes',
				'description' => $this->is_english()
					? 'When dealing with flat and non-taxable shipping fees, make sure the taxable option is set to “none” in the WooCommerce Shipping Zone Settings (WooCommerce > Settings > Shipping). Otherwise, the total cost calculation during express checkout may incur in taxes being applied to shipping fees.'
					: 'Stelle für pauschale und nicht steuerpflichtige Versandkosten sicher, dass der Steuerstatus in den Einstellungen der Versandzonen (WooCommerce > Einstellungen > Versand) auf „Keine“ gesetzt ist. Andernfalls kann es bei der Berechnung der Gesamtkosten während des Express-Checkouts zu einer fehlerhaften Berechnung von Steuern auf die Versandkosten kommen.'
			),
			'flizpay_express_checkout_pages' => array(
				'title' => $this->is_english() ? 'Pages where the express checkout is shown' : 'Seiten, auf denen der Express-Checkout angezeigt wird',
				'type' => 'multiselect', // Change to 'multiselect' to allow multiple selections
				'default' => array('product', 'cart'),
				'options' => array(
					'product' => $this->is_english() ? 'Product Page' : 'Produktseite',
					'cart' => $this->is_english() ? 'Cart Page' : 'Warenkorbseite',
				),
				'desc_tip' => true,
				'description' => $this->is_english() ? 'Select the pages where the express checkout button will appear.' : 'Wähle die Seiten aus, auf denen die Schaltfläche für den Express-Checkout angezeigt werden soll.'
			),
			'flizpay_express_checkout_theme' => array(
				'title' => $this->is_english() ? 'Express checkout button theme' : 'Design der Schaltfläche „Express-Checkout“',
				'type' => 'select',
				'default' => 'light',
				'options' => array(
					'light' => $this->is_english() ? 'Light' : 'Hell',
					'dark' => $this->is_english() ? 'Dark' : 'Dunkel',
				)
			),
		);
	}

	/**
	 * Provide the HTML for the admin banner with configuration instructions
	 * 
	 * @return string
	 * 
	 * @since 1.0.0
	 */

	private function flizpay_description_banner()
	{
		return "
			<div class='flizpay-description-banner'>
				<div class='flizpay-german-banner'>
					<div style='flizpay-banner-header'>
						<p class='flizpay-header-text'>Willkommen bei FLIZpay in WooCommerce!</p>
					</div>
					<p>Anleitung:</p>
					<ol>
						<li>Wenn du noch kein FLIZ-Firmenkonto hast, klicke bitte <a href='https://app.flizpay.de/auth/signup' target='_blank'>hier</a> und registriere dich jetzt.</li>
						<li>Nachdem du dich registriert hast, generiere bitte einen neuen API Key in deinem FLIZ-Firmenkonto und füge ihn in das unten stehende Feld “API KEY” ein. Du findest den API-Schlüssel im Abschnitt “Installation” deines FLIZ-Firmenkontos.</li>
						<li>Klicke anschließend auf den Button “Änderungen speichern”.</li>
						<li>Warte etwa 5 Sekunden, bis die Seite automatisch neu lädt.</li>
						<li>Nach dem Neuladen der Seite erscheint unten eine grüne Box, die die erfolgreiche Konfiguration des FLIZpay-Plugins anzeigt. Du bist jetzt bereit, gebührenfreie Zahlungen zu empfangen.</li>
						<li>Stelle sicher, dass du mit FLIZ so viele Gebühren wie möglich sparst. Vergiss also nicht, FLIZpay in der für Kunden zur Auswahl stehenden Liste an Zahlungsmethoden an die erste Stelle zu setzen. Klicke im linken Menü auf “WooCommerce”, dann im Untermenü auf “Einstellungen”. Klicke anschließend im Menü oben auf den Tab “Zahlungen”. Auf dieser Seite kannst du per Drag-and-Drop oder mit den Pfeilen die Position von FLIZpay in der Liste der verfügbaren Zahlungsmethoden anpassen. Klicke zum Schluss unten links auf “Änderungen speichern”.</li>
						<li>Darüber hinaus empfehlen wir dir, Rabatt für deine Kunden zu aktivieren. Ändere das Zahlungsverhalten deiner Kunden, damit sie FLIZ nutzen - denn FLIZ ist gebührenfrei. Um das zu erreichen, kannst du einen prozentualen Rabatt festlegen, der als Rabatt von jedem Einkauf abgezogen wird, den deine Kunden mit FLIZ tätigen. Je mehr Rabatt du gibst, desto höher wird der Anteil von FLIZ an deiner Kasse, was bedeutet, dass deine Kundenbasis zu einer gebührenfreien Zahlungsmethode gewechselt ist. Du kannst Rabatt in deinem FLIZ-Firmenkonto aktivieren, <a href='https://app.flizpay.de/cashback' target='_blank'>klicke hier.</a></li>
					</ol>
						Du hast noch Fragen? <a 
							href='https://flizpay.de/#faq'
							target='_blank'
							>Schau auf unserer FAQ-Seite vorbei</a>, dort findest du wahrscheinlich deine Antwort.
					<p>
						Wenn du mehr darüber erfahren möchtest, wie du dieses Plugin für deine Bedürfnisse konfigurieren kannst, <a 
							href='https://www.docs.flizpay.de/docs/intro'
							target='_blank'
							>schau dir unsere Dokumentation an.</a>
					</p>
				</div>
				<div class='flizpay-english-banner'>
					<div style='flizpay-banner-header'>
						<p class='flizpay-header-text'>Welcome to FLIZpay in WooCommerce!</p>
					</div>
					<p>Instructions:</p>
					<ol>
						<li>If you haven’t signed up for a FLIZ company account, please <a href='https://app.flizpay.de/auth/signup' target='_blank'>click here</a> and sign up.</li>
						<li>Once you have signed up, please generate a new API Key in your FLIZ company account and paste it in the “API KEY” field below. You will find the API KEY in the “Installation” section of your company account.</li>
						<li>Afterwards, click the button “Save changes”.</li>
						<li>Wait about 5 seconds and the page will reload automatically.</li>
						<li>After the page reloaded, a green box will appear at the bottom, indicating the successful configuration of the FLIZpay plugin. You are now ready to receive fee-free payments.</li>
						<li>Make sure you save as many fees as possible with fee-free FLIZ. Don’t forget to put FLIZpay first in the list of payment methods presented to customers. To do this go to the left menu and click on “WooCommerce”, then click on the sub-menu “Settings”. Now click on the tab “Payments” in the menu at the top of the page. Here, you can drag and drop or use the arrows to adjust the position of FLIZpay in the list of available payment methods. When you are finished, go to the bottom left of that page and click “Save changes”.</li>
						<li>Moreover, we advise you to activate discount for your customers. With discount, you change your customers’ payment behavior to ensure they use fee-free payment method FLIZ. This way, customers support your bottom line and optimize their own finances at the same time. You can activate discount in your FLIZ company account, <a href='https://app.flizpay.de/cashback' target='_blank'>click here.</a></li>
					</ol>
					<p>
							Still have questions? <a 
							href='https://flizpay.de/#faq'
							target='_blank'
							>Check out our FAQ page</a>, your answer will probably be there.
					</p>
					<p>
						If you'd like to know more about how to configure this plugin for your needs, <a 
							href='https://www.docs.flizpay.de/docs/intro'
							target='_blank'
							>check out our documentation.</a>
					</p>
				</div>
			</div>
		";
	}
}
