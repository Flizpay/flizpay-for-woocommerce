(function ($) {
  "use strict";

  /**
   * This JS snippet is responsible for customizing the styles of the FLIZ Settings page.
   * It will also be handling the saving of the settings
   *
   * @since 1.0.0
   */
  jQuery(document).ready(function ($) {
    new Promise((resolve) => setTimeout(resolve, 1000)).then(() => {
      console.log("FlizPay Admin JS loaded");
    });

    const connectionAttempts =
      Number.parseInt(
        localStorage.getItem("flizpay_admin_connection_attempts")
      ) || 0;
    const descriptionText = flizpayParams.wp_locale.includes("en")
      ? "Our servers have successfully communicated with your site. You're now ready to accept fee-free payments!"
      : "Unsere Server haben erfolgreich mit deiner Website kommuniziert. Du kannst jetzt gebührenfreie Zahlungen erhalten!";
    const confirmReconfigurationText = flizpayParams.wp_locale.includes("en")
      ? "Looks like you already have an integration settled up. By reconfiguring the integration you will invalidate all current ongoing payment responses. Proceed?"
      : "Sieht so aus, als ob Sie bereits eine Integration eingerichtet haben. Durch die Neukonfiguration der Integration machen Sie alle aktuellen laufenden Zahlungsantworten ungültig. Fortfahren?";
    const successfullConnectionText = flizpayParams.wp_locale.includes("en")
      ? `<p style="font-style: italic;">Connected! Waiting for the webhook confirmation. <br />
    Page will reload automatically in 5 seconds...</p>`
      : `<p>Verbunden! Warte auf die Webhook-Bestätigung. <br />
            Die Seite wird in 5 Sekunden automatisch neu geladen ...<p>`;
    const failedConnectionText = flizpayParams.wp_locale.includes("en")
      ? `An error occurred while testing the connection. Please Try Again. <br />`
      : `Beim Testen der Verbindung ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut. <br />`;
    const flexibleShippingDisclaimer = flizpayParams.wp_locale.includes("en")
      ? `Are you using the plugin <a href="https://wordpress.org/plugins/flexible-shipping/">"Flexible Shipping"</a>? 
Then make sure you set the shipping calculation on the Package and not on the Cart. For express checkout the cart is not required and when Flexible Shipping is not configured for Package calculation it won't be presented as a shipping option in FLIZ app.`
      : `Verwenden Sie das Plugin <a href="https://wordpress.org/plugins/flexible-shipping/">„Flexible Shipping“</a>?
Stellen Sie dann sicher, dass Sie die Versandkostenberechnung auf dem Paket und nicht auf dem Warenkorb festlegen. Für den Express-Checkout ist der Warenkorb nicht erforderlich. Wenn „Flexible Shipping“ nicht für die Paketberechnung konfiguriert ist, wird er in der FLIZ-App nicht als Versandoption angezeigt.`;
    const adminOptionTitle = flizpayParams.wp_locale.includes("en")
      ? "Admin Display Options"
      : "Anzeigeoptionen für Admins";
    const flexibleShippingParagraph = document.createElement("p");
    const testButton = document.createElement("div");
    const resultField = document.createElement("div");
    const apiKeyInput = document.querySelector(
      "#woocommerce_flizpay_flizpay_api_key"
    );
    // Safely access the value or default to empty string if element doesn't exist
    const initialApiKeyValue = apiKeyInput ? apiKeyInput.value : "";
    const displayHeadlineInput = document.querySelector(
      "#woocommerce_flizpay_flizpay_display_headline"
    );
    const displayHeadlineLabel = document.querySelector("#displayHeadline");
    const webhookURLInput = document.querySelector(
      "#woocommerce_flizpay_flizpay_webhook_url"
    );
    const enabledCheckbox = document.querySelector(
      "#woocommerce_flizpay_flizpay_enabled"
    );
    const webhookAlive = document.querySelector(
      "#woocommerce_flizpay_flizpay_webhook_alive"
    );
    const description = document.querySelector(
      "#connection-stablished-description"
    );
    const orderStatus = document.querySelector(
      "#woocommerce_flizpay_flizpay_order_status"
    );
    const expressCheckoutButtonTheme = document.querySelector(
      "#woocommerce_flizpay_flizpay_express_checkout_theme"
    );
    const divider = document.createElement("hr");
    const divider2 = document.createElement("hr");
    const divider3 = document.createElement("hr");
    const dividerRow = document.createElement("tr");
    const dividerRow2 = document.createElement("tr");
    const dividerRow3 = document.createElement("tr");
    const exampleImage = document.createElement("img");
    const checkoutSectionTitle = document.createElement("h2");
    const expressCheckoutSectionTitle = document.createElement("h2");
    const orderStatusLabel = document.createElement("h2");
    const expressCheckoutButtonLight = document.createElement("img");
    const expressCheckoutButtonDark = document.createElement("img");
    const buttonExampleLabel = document.createElement("p");

    // Remove the express checkout theme selection from the settings page
    document.querySelector(
      "#woocommerce_flizpay_flizpay_express_checkout_theme"
    ).parentNode.parentElement.parentElement.style.display = "none";

    initCustomAttributesAndStyles();

    if (isConnectionFailed()) {
      renderConnectionFailed();
    } else if (isConnectionPending() && reachedMaxAttempts()) {
      renderConnectionFailed();
    } else if (isConnectionPending()) {
      renderWaitingConnection();
      scheduleReloadAndIncreaseCounter();
    } else {
      localStorage.removeItem("flizpay_admin_connection_attempts");
    }

    $("form").on("submit", (e) => {
      if (hasChangedApiKey() && !confirm(confirmReconfigurationText)) {
        e.preventDefault();
      }
    });

    function reachedMaxAttempts() {
      return connectionAttempts === 10;
    }

    function hasChangedApiKey() {
      const currentApiKey = document.querySelector(
        "#woocommerce_flizpay_flizpay_api_key"
      );
      return (
        webhookAlive &&
        webhookAlive.checked &&
        currentApiKey &&
        currentApiKey.value !== initialApiKeyValue
      );
    }

    function renderConnectionFailed() {
      localStorage.removeItem("flizpay_admin_connection_attempts");
      resultField.classList.add("connection-failed");
      resultField.innerHTML = `
        ${failedConnectionText}
        <img src='${flizpayParams.loading_icon}' />
      `;
    }

    function renderWaitingConnection() {
      resultField.classList.add("connection-success");
      resultField.innerHTML = `
        ${successfullConnectionText}
        <img src='${flizpayParams.loading_icon}' />
      `;
    }

    function scheduleReloadAndIncreaseCounter() {
      localStorage.setItem(
        "flizpay_admin_connection_attempts",
        connectionAttempts + 1
      );
      setTimeout(() => {
        window.location.reload();
      }, 5000);
    }

    function isConnectionFailed() {
      return (
        webhookURLInput &&
        webhookURLInput.value.length !== 0 &&
        (!apiKeyInput || apiKeyInput.value.length === 0)
      );
    }

    function isConnectionPending() {
      return (
        webhookURLInput &&
        webhookURLInput.value.length !== 0 &&
        (!webhookAlive || !webhookAlive.checked)
      );
    }

    function initCustomAttributesAndStyles() {
      if (
        !jQuery("#woocommerce_flizpay_flizpay_enable_express_checkout").is(
          ":checked"
        )
      ) {
        flexibleShippingParagraph.innerHTML = flexibleShippingDisclaimer;
        expressCheckoutButtonTheme.setAttribute("disabled", true);
        document
          .querySelector("#woocommerce_flizpay_flizpay_express_checkout_pages")
          .setAttribute("disabled", true);
      }
      jQuery("#woocommerce_flizpay_flizpay_express_checkout_pages").select2({
        placeholder: flizpayParams.wp_locale.includes("en")
          ? "Select pages..."
          : "Seiten auswählen",
        allowClear: true,
        width: "100%",
        tags: true, // Allows dynamic adding and removal
      });
      flizpayParams.wp_locale.includes("en")
        ? document
            .querySelector(".flizpay-german-banner")
            .setAttribute("style", "display: none;")
        : document
            .querySelector(".flizpay-english-banner")
            .setAttribute("style", "display: none;");
      exampleImage.setAttribute("src", flizpayParams.example_image);
      exampleImage.setAttribute("width", "40%");
      expressCheckoutButtonDark.setAttribute(
        "src",
        flizpayParams.express_checkout_button_dark
      );
      expressCheckoutButtonDark.setAttribute(
        "width",
        expressCheckoutButtonTheme.offsetWidth
      );
      expressCheckoutButtonDark.setAttribute("style", "margin-top: 10px;");
      expressCheckoutButtonLight.setAttribute(
        "src",
        flizpayParams.express_checkout_button_light
      );
      expressCheckoutButtonLight.setAttribute(
        "width",
        expressCheckoutButtonTheme.offsetWidth
      );
      expressCheckoutButtonLight.setAttribute("style", "margin-top: 10px;");
      testButton.setAttribute("id", "woocommerce_flizpay_test_connection");
      resultField.setAttribute("id", "woocommerce_flizpay_connection_result");
      // Only append if apiKeyInput exists
      if (apiKeyInput && apiKeyInput.parentNode) {
        apiKeyInput.parentNode.appendChild(testButton);
        apiKeyInput.parentNode.appendChild(resultField);
      }
      // Safely set attributes on elements, checking if they exist first
      if (webhookURLInput) {
        webhookURLInput.setAttribute("disabled", true);
        webhookURLInput.setAttribute("type", "hidden");
      }
      if (enabledCheckbox) {
        enabledCheckbox.setAttribute("disabled", true);
      }
      if (webhookAlive) {
        webhookAlive.setAttribute("disabled", true);
      }

      divider.setAttribute("style", "width: 100%");
      divider2.setAttribute("style", "width: 100%");
      divider3.setAttribute("style", "width: 100%");
      dividerRow.setAttribute(
        "style",
        "width: 80vw; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; padding: 10px; text-align: center;"
      );
      dividerRow2.setAttribute(
        "style",
        "width: 80vw; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; padding: 10px; text-align: center; gap: 20px;"
      );
      dividerRow3.setAttribute(
        "style",
        "width: 80vw; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; padding: 10px; text-align: center; gap: 20px;"
      );
      checkoutSectionTitle.setAttribute("style", "width: 100%;");
      expressCheckoutSectionTitle.setAttribute("style", "width: 100%;");
      checkoutSectionTitle.innerHTML = flizpayParams.wp_locale.includes("en")
        ? "Checkout Settings"
        : "Kasse Einstellung";
      expressCheckoutSectionTitle.innerHTML = flizpayParams.wp_locale.includes(
        "en"
      )
        ? "Express Checkout Settings"
        : "Express-Checkout Einstellung";
      dividerRow.append(divider);
      dividerRow.appendChild(checkoutSectionTitle);
      dividerRow.append(exampleImage);
      dividerRow2.append(divider2);
      dividerRow2.appendChild(expressCheckoutSectionTitle);
      dividerRow2.append(flexibleShippingParagraph);
      buttonExampleLabel.innerHTML = flizpayParams.wp_locale.includes("en")
        ? "Example:"
        : "Beispiel:";

      // Only append elements if parent exists
      if (expressCheckoutButtonTheme && expressCheckoutButtonTheme.parentNode) {
        expressCheckoutButtonTheme.parentNode.append(buttonExampleLabel);
        expressCheckoutButtonTheme.parentNode.append(expressCheckoutButtonDark);
        expressCheckoutButtonTheme.parentNode.append(
          expressCheckoutButtonLight
        );
      }

      orderStatusLabel.innerHTML = adminOptionTitle;
      dividerRow3.append(divider3);
      dividerRow3.append(orderStatusLabel);

      if (orderStatus) {
        orderStatus.append(dividerRow2);
      }

      const table = document.querySelector("table > tbody");
      if (table) {
        const tr3 = table.querySelector("tr:nth-child(3)");
        const tr9 = table.querySelector("tr:nth-child(9)");
        const tr7 = table.querySelector("tr:nth-child(7)");

        if (tr3) tr3.insertAdjacentElement("afterend", dividerRow);
        if (tr9) tr9.insertAdjacentElement("afterend", dividerRow2);
        if (tr7) tr7.insertAdjacentElement("afterend", dividerRow3);
      }

      if (webhookAlive && webhookAlive.checked && description) {
        description.setAttribute(
          "style",
          "color: #001F3F; background-color: #80ED99; padding: 10px; font-weight: bold; margin-top: 30px;"
        );
        description.innerHTML = descriptionText;
      }

      if (displayHeadlineLabel && displayHeadlineInput) {
        displayHeadlineLabel.setAttribute(
          "style",
          displayHeadlineInput.checked ? "display: none;" : "display: block;"
        );
      }

      if (expressCheckoutButtonTheme) {
        expressCheckoutButtonTheme.value === "light"
          ? (expressCheckoutButtonDark.style.display = "none")
          : (expressCheckoutButtonLight.style.display = "none");

        jQuery(expressCheckoutButtonTheme).on("change", function () {
          if (this.value === "light") {
            jQuery(expressCheckoutButtonDark).hide();
            jQuery(expressCheckoutButtonLight).show();
          } else {
            jQuery(expressCheckoutButtonLight).hide();
            jQuery(expressCheckoutButtonDark).show();
          }
        });
      }

      if (displayHeadlineInput && displayHeadlineLabel) {
        jQuery(displayHeadlineInput).on("change", () => {
          if (!displayHeadlineInput.checked) {
            displayHeadlineLabel.setAttribute("style", "display: block;");
          } else {
            displayHeadlineLabel.setAttribute("style", "display: none;");
          }
        });
      }

      jQuery("#woocommerce_flizpay_flizpay_enable_express_checkout").on(
        "change",
        function () {
          if (!this.checked) {
            if (expressCheckoutButtonTheme) {
              expressCheckoutButtonTheme.setAttribute("disabled", true);
            }
            const expressCheckoutPages = document.querySelector(
              "#woocommerce_flizpay_flizpay_express_checkout_pages"
            );
            if (expressCheckoutPages) {
              expressCheckoutPages.setAttribute("disabled", true);
            }
          } else {
            if (expressCheckoutButtonTheme) {
              expressCheckoutButtonTheme.removeAttribute("disabled");
            }
            const expressCheckoutPages = document.querySelector(
              "#woocommerce_flizpay_flizpay_express_checkout_pages"
            );
            if (expressCheckoutPages) {
              expressCheckoutPages.removeAttribute("disabled");
            }
          }
        }
      );
    }
  });
})(jQuery);
