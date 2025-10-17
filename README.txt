=== FLIZpay Gateway für WooCommerce ===
Contributors: Flizpay
Tags: kostenlos, payments, Zahlung, cashback, no-fee
Requires at least: 4.4
Tested up to: 6.8
Stable tag: 2.4.15
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Mit dem FLIZpay-Plugin kannst du die Zahlungsmethode FLIZ in deinen Checkout integrieren. FLIZ ist für Shops und Zahlende gebührenfrei.

== Description ==
## Was macht das FLIZpay Plugin?

FLIZ ist die erste kostenlose Zahlungsmethode in Deutschland. Deine Kunden zahlen mit der FLIZpay App, und du erhältst diese Zahlungen ohne Gebühren mit dem FLIZpay Plugin. Du kannst FLIZ ohne technisches Wissen in weniger als 30 Minuten installieren und sofort Zahlungen annehmen.

### Verbessere deine Conversion und Kundenbindung mit Cashback

Ändere das Zahlungsverhalten deiner Kunden, damit sie FLIZ nutzen. Denn FLIZ ist gebührenfrei. Um dies zu erreichen, kannst du einen prozentualen Cashback festlegen, der als Rabatt von jedem Einkauf abgezogen wird, den deine Kunden mit FLIZ tätigen. So unterstützen deine Kunden dein Geschäftsergebnis und optimieren gleichzeitig ihre eigenen Finanzen.

### Sichere Zahlungen

FLIZ sichert deine Transaktionen mit einer vollständigen Ende-zu-Ende-Verschlüsselung und gewährleistet maximale Sicherheit. Indem Zahlungen über das SEPA-Netzwerk abgewickelt werden, unterliegt jede Zahlung denselben strengen Sicherheitsprüfungen wie alle Überweisungen innerhalb der EU.

Mit FLIZ können Kunden mit deutschem Bankkonto zahlen & Unternehmen Zahlungen auf allen EU-Konten empfangen.

## Dienste eines Drittanbieters

Dieses Plugin nutzt den FLIZpay-API-Service zur Abwicklung von Zahlungen. Bei der Verwendung dieses Plugins werden bestimmte Daten an die Server von FLIZpay gesendet.
Wenn ein Nutzer eine Zahlung über das Plugin abschließt, werden folgende Daten zur Verarbeitung an FLIZpay übermittelt:

- Transaktionsdetails (Betrag, Währung, usw.)
- Name des Händlers und, falls festgelegt, der Cashback-Wert.

### Service Informationen:
- **Service**: [FLIZpay API](https://api.flizpay.de)
- **Datenschutzerklärung**: [FLIZpay Privacy Policy](https://flizpay.de/privacy-policy)
- **Allgemeine Geschäftsbedingungen**: [FLIZpay Terms and Conditions](https://www.flizpay.de/business-terms-and-conditions)

Durch die Nutzung dieses Plugins stimmst du den AGBs und der Datenschutzerklärung von FLIZpay zu.

Für weitere Informationen zur FLIZpay-API und wie deine Daten verarbeitet werden, siehe bitte unsere [Dokumentation für Entwickler](https://www.docs.flizpay.de/docs/category/woocommerce).

## Erfordert
Dieses Plugin erfordert Folgendes:
- WooCommerce (Version 9.0.0 oder höher): [https://wordpress.org/plugins/woocommerce/](https://wordpress.org/plugins/woocommerce/)

## Telemetrie & anonyme Fehlerberichte (Opt-out)
Um die Stabilität und Sicherheit des FLIZpay-Plugins weiter zu verbessern, kannst du freiwillig **anonyme Nutzungs- und Fehlerdaten** an unseren Sentry-Server übermitteln lassen.
- **Welche Daten werden erfasst?**
  – zufällige, nicht zurückverfolgbare Instanz-ID
  – WordPress-, WooCommerce-, PHP- und Plugin-Version
  – aktive Theme-/Plugin-Liste (nur Slugs, keine Lizenz- oder Zugangsdaten)
  – Zeitstempel, Fehlermeldung, Stack-Trace
- **Was *nicht* übertragen wird?**
  Keine personenbezogenen Daten (PII), keine Bestelldetails, keine IP-Adresse (wird gehasht), kein Kunden-Name, keine E-Mail.
- **Wann wird gesendet?**
  **Nur nach ausdrücklicher Zustimmung** (Opt-in). Du kannst die Zustimmung jederzeit unter
  „WooCommerce → Einstellungen → Zahlungen → FLIZpay → Fehlerberichte.
- **Drittanbieter-Service**
  – **Service:** Sentry Error Monitoring (sentry.io)
  – **Datenschutzerklärung:** <https://sentry.io/privacy/>
Weitere Informationen findest du in unserer [Datenschutzerklärung](https://flizpay.de/privacy-policy).

== Screenshots ==

1. Ansicht, wenn FLIZpay in deinem Checkout ausgewählt ist.
2. Ansicht, wenn FLIZpay in deinem Checkout nicht ausgewählt ist.

== Installation ==

Der erste Schritt, um FLIZpay in deinem Checkout zu installieren, ist die Erstellung eines Kontos bei uns. [Gehe auf unsere Website](https://app.flizpay.de), erstelle ein Konto und folge den Anweisungen im Menüpunkt "Installation".

== Changelog ==

- 1.0.0
  - ADDED: Plugin Settings, Cashback info on Checkout Order management for cashback

- 1.0.1
  - FIXED: Plugin Readme, Docs and Data Privacy links

- 1.0.2
  - FIXED: Fixed Logo size for some themes, Updated checkout description and translations

- 1.1.0
  - ADDED: Refund for discounted items and New Logo
  - FIXED: Payment failed page adjustment

- 1.2.0
  - ADDED: Customization of the checkout button
  - FIXED: Default translations for non supported languages

- 1.2.1
  - FIXED: Old payment page was still being displayed

- 1.2.2
  - ADDED: Cancel button to mobile loading modal on checkout

- 1.2.3
  - HOTIFX: FLIZ logo not showing on mobile loading checkout modal

- 1.2.4
  - ADDED: Localstorage to store ongoing orders, Properly translated the admin page 
  - FIXED: Loadin wheel layout

- 1.3.0
  - CHANGED: Admin page configuration UX
  - REMOVED: Payment Failed page

- v1.4.0
  - CHANGED: Admin page uses admin user language
  - ADDED: Adding customer information to the transaction being created
  - ADDED: Value with cashback applied, when active, is now displayed in the checkout page

- v1.4.1
  - REMOVED: Reverted value with cashback at the checkout page
  - CHANGED: Increased time of the cashback cached value to 10 min

- v1.4.2
  - CHANGED: Create order in draft mode to prevent accountability softwares from picking the order in pending

- v1.4.3
  - CHANGED: Dynamic cashback value for first purchase and standard options
  - CHANGED: Remove the webhook url on uninstall
  - CHANGED: Disable new order emails for FLIZpay orders

- v1.4.4
  - HOTFIX: Cashback data typecheck for backwards compatibility

- v1.4.5
  - HOTFIX: Total order value could have been stored incorrectly due to taxes variation

- v2.0.0
  - ADDED: Express checkout functionality

- v2.0.1
  - FIXED: Express checkout functionality files

- v2.0.2
  - FIXED: Express checkout button font size

- v2.0.3
  - FIXED: Fix express checkout shipping taxes, german market fees calculation

- v2.0.4
  - FIXED: Fix express checkout for items on sale, small adjustments on loading and translations
  - ADDED: Set business as active/inactive on plugin activation/deactivation

- v2.0.5
  - FIXED: Express checkout button font size, and Emails not being sent for orders paid with FLIZpay

- v2.0.6
  - FIXED: Translations for checkout buttons
  - ADDED: Instant cashback info update when settings change on FLIZ web app

- v2.0.7
  - FIXED: German translation on loading wheel

- v2.1.0
  - ADDED: Support for non-shippable products
  - FIXED: Title length of express checkout button
  - REMOVED: Status draft for new orders 

- v2.2.0
  - ADDED: Support for custom order status
  - REMOVED: Google fonts

- v2.2.1
  - FIXED: Plugin upgrader functionality compatibility with older versions

- v2.3.0
  - ADDED: Improved the webhook registration flow

- v2.4.1
  - FIXED: Admin page inputs being loaded incorrectly
  - FIXED: Gateway variables declaration

- v2.4.2
  - FIXED: Order total value not being stored correctly due to taxes variation
  - FIXED: Admin page inputs trying to be accessed before they were loaded
  - REMOVED: Legacy failure page
  - FIXED: Now disables the express checkout button if the cart is empty
  - FIXED: Explicitly set FLIZpay as the payment method for orders created by the plugin

- v2.4.3
  - FIXED - Shipping costs sometimes not appearing in total cost

- v2.4.4
  - ADDED - Plugin version to frontend

- v2.4.5
  - FIXED - Webhook URL missing HTTPS protocol

- v2.4.6
  - FIXED - Checkout script renamed to avoid Germanized conflicts

- v2.4.7
  - FIXED - Total cost calculation for express checkout now happens only in WooCommerce, preventing mismatches

- v2.4.9
  - FIXED - German localization: Corrected the cashback description and payment confirmation message,
  - FIXED - Shipping API: Ensured needs_shipping_method now always returns a boolean.
  - ADDED - Sentry integration: Enabled error tracking for both checkout and product flows.
  - ADDED - Admin option: Introduced a new “Error Reporting” toggle in the admin panel to turn Sentry on or off.

- v2.4.10
  - FIXED - Sentry Integration: Ensured only FLIZpay errors are caught.

- v2.4.11
  - Fixed - Sentry Integration: Fixed sentry loading error.

- v2.4.12
  - Added - Product name to express checkout order
  - Fixed – Shipping options not shown for addresses with house number/sub-unit format.

- v2.4.13
  - Added - Support for Wordpress version 6.8

- v2.4.14
  - Removed - Express checkout feature

- v2.4.15
  - Fixed - Admin JavaScript now only loads on FLIZpay admin screens instead of every dashboard page

== Upgrade Notice ==

= 2.4.15 =
* Fixed - Admin JavaScript now only loads on FLIZpay admin screens instead of every dashboard page

= 2.4.14 =
* Removed - Express checkout feature

= 2.4.13 =
* Added - Support for Wordpress version 6.8

= 2.4.12 =
* Added - Product name to express checkout order
* Fixed – Shipping options not shown for addresses with house number/sub-unit format.

= 2.4.11 =
* Fixed - Sentry Integration: Fixed sentry loading error.

= 2.4.10 =
* FIXED - Sentry Integration: Ensured only FLIZpay errors are caught.

= 2.4.9 =
* FIXED - German localization: Corrected the cashback description and payment confirmation message,
* FIXED - Shipping API: Ensured needs_shipping_method now always returns a boolean.
* ADDED - Sentry integration: Enabled error tracking for both checkout and product flows.
* ADDED - Admin option: Introduced a new “Error Reporting” toggle in the admin panel to turn Sentry on or off.

= 2.4.7 =
* FIXED - Total cost calculation for express checkout now happens only in WooCommerce, preventing mismatches

= v2.4.6 =
* FIXED - Checkout script renamed to avoid Germanized conflicts

= 2.4.5 =
* Fixed - Webhook URL missing HTTPS protocol

= 2.4.4 =
* Added - Plugin version to frontend

= 2.4.3 =
* Hotfix - Shipping costs sometimes not appearing in total cost

= 2.4.2 =
* Hotfix for order total value not being stored correctly due to taxes variation
* Hotfix for admin page inputs trying to be accessed before they were loaded
* Removed legacy failure page
* Hotfix for express checkout button being disabled if the cart is empty
* Hotfix for explicitly setting FLIZpay as the payment method for orders created by the plugin

= 2.4.1 =
* Hotfix for gateway variables declaration and admin page inputs not loading correctly

= 2.3.0 =
* Improved the webhook registration flow

= 2.2.1 =
* Fixed plugin upgrader functionality compatibility with older versions

= 2.2.0 =
* Added support for custom order status and removed google fonts

= 2.1.0 =
* Added support for non-shippable products, fixed title length of express checkout button and removal of status draft for new orders

= 2.0.7 =
* Fix german translation on the mobile loading wheel

= 2.0.6 =
* Fix translation for checkout buttons and implement instant cashback info from FLIZ web app

= 2.0.5 =
* Hotfix for express checkout button font size and emails not being sent for orders paid with FLIZpay

= 2.0.4 =
* Fix for express checkout for items on sale, small adjustments on loading and translations

= 2.0.3 =
* Hotfix for express checkout shipping taxes and german market fees calculation

= 2.0.2 =
* Hotfix for express checkout button font size

= 2.0.1 =
* Hotfix for express checkout functionality files

= 2.0.0 =
* Seamless checkout experience with the new express checkout functionality

= v1.4.5 =
* Hotfix total order value could have been stored incorrectly due to taxes variation

= v1.4.4 =
* Hotfix for cashback data type backwards compatibility

= v1.4.3 =
* Dynamic cashback value for first purchase and standard options
* Remove the webhook url on uninstall
* Disable new order emails for FLIZpay orders

= v1.4.2 =
* Orders are now created in draft mode to prevent being picked by other softwares while in pending state

= v1.4.1 =
* Reverted value with cashback at the checkout page and increased cached time for cashback value

= v1.4.0 =
* The admin page now is presented on the admin user language or defaults to system language. Customer information add to the Transaction and value with cashback displayed on the checkout page

= v1.3.0 = 
* The admin page now has a better UX and the payment failed page was removed in favor of FLIZ payment failed page

= v1.2.4 = 
* Localstorage order storage, admin page translation and fixed on loading wheel layout

= v1.2.3 = 
* Hotfixed the FLIZ logo not showing on the mobile checkout loading modal

= v1.2.2 =
* A cancel button is now available for mobile checkout

= v1.2.1 =
* Old payment failed page is no longer displayed

= v1.2.0 =
* Checkout button customization and fix on default translations

= v1.1.0 =
* Refund for discounted items, new logo and pages adjustments

= v1.0.2 = 
* Logo and Descriptions fixes

= v1.0.1 = 
* Readme and Documentation fixes

= v1.0.0 =
* Version 1.0.0 FLIZpay Gateway für WooCommerce Plugin