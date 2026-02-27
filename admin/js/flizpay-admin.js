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
    const adminOptionTitle = flizpayParams.wp_locale.includes("en")
      ? "Admin Display Options"
      : "Anzeigeoptionen für Admins";
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
    const divider = document.createElement("hr");
    const divider3 = document.createElement("hr");
    const dividerRow = document.createElement("tr");
    const dividerRow3 = document.createElement("tr");
    const exampleImage = document.createElement("img");
    const checkoutSectionTitle = document.createElement("h2");
    const orderStatusLabel = document.createElement("h2");

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
      flizpayParams.wp_locale.includes("en")
        ? document
            .querySelector(".flizpay-german-banner")
            .setAttribute("style", "display: none;")
        : document
            .querySelector(".flizpay-english-banner")
            .setAttribute("style", "display: none;");
      exampleImage.setAttribute("src", flizpayParams.example_image);
      exampleImage.setAttribute("width", "40%");

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

      // Add unique classes to our divider rows to make them easier to find/remove
      dividerRow.classList.add("flizpay-divider", "checkout-section");
      dividerRow3.classList.add("flizpay-divider", "admin-options-section");

      // Remove any existing dividers first to avoid duplicates
      const existingDividers = document.querySelectorAll(".flizpay-divider");
      existingDividers.forEach((div) => {
        if (div.parentNode) {
          div.parentNode.removeChild(div);
        }
      });

      // Set styles for dividers and titles
      divider.setAttribute("style", "width: 100%");
      divider3.setAttribute("style", "width: 100%");

      const dividerStyle =
        "width: 80vw; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; padding: 10px; text-align: center;";
      dividerRow.setAttribute("style", dividerStyle);
      dividerRow3.setAttribute("style", dividerStyle + " gap: 20px;");

      checkoutSectionTitle.setAttribute("style", "width: 100%;");

      // Set section titles
      checkoutSectionTitle.innerHTML = flizpayParams.wp_locale.includes("en")
        ? "Checkout Settings"
        : "Kasse Einstellung";
      orderStatusLabel.innerHTML = adminOptionTitle;

      // Build checkout section divider
      dividerRow.append(divider);
      dividerRow.appendChild(checkoutSectionTitle);
      dividerRow.append(exampleImage);

      // Build admin options section divider
      dividerRow3.append(divider3);
      dividerRow3.append(orderStatusLabel);

      // Find the main settings table
      const table = document.querySelector("table.form-table > tbody");
      if (!table) return;

      // Add checkout section after Connection Established section
      const connectionEstablishedRow = table.querySelector(
        "tr:has(#woocommerce_flizpay_flizpay_webhook_alive)"
      );

      // Try finding the row with the connection description
      const connectionDescriptionRow =
        connectionEstablishedRow ||
        (description ? description.closest("tr") : null);

      if (connectionDescriptionRow) {
        connectionDescriptionRow.insertAdjacentElement("afterend", dividerRow);
      } else {
        // Fallback: use the original approach if connection row not found
        const apiKeyRow =
          table.querySelector("tr:has(#woocommerce_flizpay_flizpay_api_key)") ||
          table.querySelector("tr:nth-child(3)");
        if (apiKeyRow) {
          apiKeyRow.insertAdjacentElement("afterend", dividerRow);
        }
      }

      // Add admin options section before order status
      const orderStatusRow = table.querySelector(
        "tr:has(#woocommerce_flizpay_flizpay_order_status)"
      );
      if (orderStatusRow) {
        orderStatusRow.insertAdjacentElement("beforebegin", dividerRow3);
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

      if (displayHeadlineInput && displayHeadlineLabel) {
        jQuery(displayHeadlineInput).on("change", () => {
          if (!displayHeadlineInput.checked) {
            displayHeadlineLabel.setAttribute("style", "display: block;");
          } else {
            displayHeadlineLabel.setAttribute("style", "display: none;");
          }
        });
      }
    }
  });
})(jQuery);
