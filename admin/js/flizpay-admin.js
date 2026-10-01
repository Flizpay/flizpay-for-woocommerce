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
        localStorage.getItem("flizpay_admin_connection_attempts"),
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
      "#woocommerce_flizpay_flizpay_api_key",
    );
    // Safely access the value or default to empty string if element doesn't exist
    const initialApiKeyValue = apiKeyInput ? apiKeyInput.value : "";
    const displayHeadlineInput = document.querySelector(
      "#woocommerce_flizpay_flizpay_display_headline",
    );
    const displayHeadlineLabel = document.querySelector("#displayHeadline");
    const displayLogoInput = document.querySelector(
      "#woocommerce_flizpay_flizpay_display_logo",
    );
    const displayDescriptionInput = document.querySelector(
      "#woocommerce_flizpay_flizpay_display_description",
    );
    const webhookURLInput = document.querySelector(
      "#woocommerce_flizpay_flizpay_webhook_url",
    );
    const webhookAlive = document.querySelector(
      "#woocommerce_flizpay_flizpay_webhook_alive",
    );
    const description = document.querySelector(
      "#connection-stablished-description",
    );
    const divider = document.createElement("hr");
    const divider2 = document.createElement("hr");
    const divider3 = document.createElement("hr");
    const dividerRow = document.createElement("tr");
    const dividerRow2 = document.createElement("tr");
    const dividerRow3 = document.createElement("tr");
    const checkoutSectionTitle = document.createElement("h2");
    const placementSectionTitle = document.createElement("h2");
    const orderStatusLabel = document.createElement("h2");

    // Live checkout-preview sub-elements (assigned in buildCheckoutPreview).
    let previewTitleEl = null;
    let previewLogoEl = null;
    let previewDescriptionEl = null;

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
        "#woocommerce_flizpay_flizpay_api_key",
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
        connectionAttempts + 1,
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
      if (webhookAlive) {
        webhookAlive.setAttribute("disabled", true);
      }

      // Add unique classes to our divider rows to make them easier to find/remove
      dividerRow.classList.add("flizpay-divider", "checkout-section");
      dividerRow2.classList.add("flizpay-divider", "placement-section");
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
      divider2.setAttribute("style", "width: 100%");
      divider3.setAttribute("style", "width: 100%");

      const dividerStyle =
        "width: 80vw; display: flex; flex-wrap: wrap; justify-content: center; align-items: center; padding: 10px; text-align: center;";
      dividerRow.setAttribute("style", dividerStyle);
      dividerRow2.setAttribute("style", dividerStyle);
      dividerRow3.setAttribute("style", dividerStyle + " gap: 20px;");

      checkoutSectionTitle.setAttribute("style", "width: 100%;");
      placementSectionTitle.setAttribute("style", "width: 100%;");

      // Set section titles
      checkoutSectionTitle.innerHTML = flizpayParams.wp_locale.includes("en")
        ? "Checkout Settings"
        : "Kasse Einstellung";
      placementSectionTitle.innerHTML = flizpayParams.wp_locale.includes("en")
        ? "On-site Messaging"
        : "Shop-Hinweise";
      orderStatusLabel.innerHTML = adminOptionTitle;

      // Build checkout section divider
      dividerRow.append(divider);
      dividerRow.appendChild(checkoutSectionTitle);
      const checkoutPreview = buildCheckoutPreview();
      if (checkoutPreview) {
        dividerRow.append(checkoutPreview);
      }

      // Build on-site messaging section divider
      dividerRow2.append(divider2);
      dividerRow2.append(placementSectionTitle);
      dividerRow2.append(buildPlacementPreview());

      // Build admin options section divider
      dividerRow3.append(divider3);
      dividerRow3.append(orderStatusLabel);

      // Find the main settings table
      const table = document.querySelector("table.form-table > tbody");
      if (!table) return;

      // Add checkout section after Connection Established section
      const connectionEstablishedRow = table.querySelector(
        "tr:has(#woocommerce_flizpay_flizpay_webhook_alive)",
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

      // Add on-site messaging section before its checkbox group
      const placementRow = table.querySelector(
        "tr:has(#woocommerce_flizpay_flizpay_placement_product)",
      );
      if (placementRow) {
        placementRow.insertAdjacentElement("beforebegin", dividerRow2);
      }

      // Add admin options section before order status
      const orderStatusRow = table.querySelector(
        "tr:has(#woocommerce_flizpay_flizpay_order_status)",
      );
      if (orderStatusRow) {
        orderStatusRow.insertAdjacentElement("beforebegin", dividerRow3);
      }

      if (webhookAlive && webhookAlive.checked && description) {
        description.setAttribute(
          "style",
          "color: #001F3F; background-color: #80ED99; padding: 10px; font-weight: bold; margin-top: 30px;",
        );
        description.innerHTML = descriptionText;
      }

      if (displayHeadlineLabel && displayHeadlineInput) {
        displayHeadlineLabel.setAttribute(
          "style",
          displayHeadlineInput.checked ? "display: none;" : "display: block;",
        );
      }

      if (displayHeadlineInput) {
        jQuery(displayHeadlineInput).on("change", () => {
          if (displayHeadlineLabel) {
            displayHeadlineLabel.setAttribute(
              "style",
              displayHeadlineInput.checked
                ? "display: none;"
                : "display: block;",
            );
          }
          updatePreviewTitle();
        });
      }

      if (displayLogoInput) {
        jQuery(displayLogoInput).on("change", updatePreviewLogo);
      }

      if (displayDescriptionInput) {
        jQuery(displayDescriptionInput).on("change", updatePreviewDescription);
      }
    }

    /**
     * Decode HTML entities (e.g. &ndash;) coming from server-side, localized
     * strings without relying on window.wp.htmlEntities (not loaded in admin).
     * The decoded value is always assigned to the DOM via textContent by the
     * callers, so this never introduces an innerHTML/XSS surface.
     */
    function decodeEntities(str) {
      if (!str) return "";
      const textarea = document.createElement("textarea");
      textarea.innerHTML = str;
      return textarea.value;
    }

    /**
     * Build the interactive checkout preview that mirrors the FLIZpay block
     * checkout label (see public/js/flizpay-checkout.js LabelElement). Returns
     * null when no preview data was localized (e.g. gateway unavailable), so
     * the rest of the settings page is left intact.
     */
    function buildCheckoutPreview() {
      const data = flizpayParams.checkout_preview;
      if (!data) return null;

      const container = document.createElement("div");
      container.classList.add("flizpay-checkout-preview");

      const option = document.createElement("label");
      option.classList.add("flizpay-checkout-preview__option");

      const inputWrapper = document.createElement("div");
      inputWrapper.classList.add("flizpay-checkout-preview__input-wrapper");

      const input = document.createElement("div");
      input.classList.add("flizpay-checkout-preview__input");

      const labelGroup = document.createElement("div");
      labelGroup.classList.add("flizpay-checkout-preview__label-group");

      const label = document.createElement("div");
      label.classList.add("flizpay-checkout-preview__label");

      previewTitleEl = document.createElement("span");
      previewTitleEl.classList.add("flizpay-preview-title");

      previewLogoEl = document.createElement("img");
      previewLogoEl.classList.add("flizpay-preview-logo");
      previewLogoEl.setAttribute("width", "68");
      previewLogoEl.setAttribute("height", "24");
      previewLogoEl.setAttribute("src", data.logoUrl);
      previewLogoEl.setAttribute("alt", "FLIZpay");

      label.append(previewTitleEl);
      label.append(previewLogoEl);
      labelGroup.append(label);

      previewDescriptionEl = document.createElement("div");
      previewDescriptionEl.classList.add(
        "flizpay-checkout-preview__description",
        "flizpay-preview-description",
      );
      previewDescriptionEl.textContent = decodeEntities(data.description);

      inputWrapper.append(input);
      option.append(inputWrapper);
      option.append(labelGroup);
      container.append(option);
      container.append(previewDescriptionEl);

      // Initialize from the currently saved checkbox states so the preview
      // opens matching what is saved, then live-updates on change.
      updatePreviewTitle();
      updatePreviewLogo();
      updatePreviewDescription();

      return container;
    }

    function updatePreviewTitle() {
      if (!previewTitleEl || !flizpayParams.checkout_preview) return;
      const showHeadline = displayHeadlineInput
        ? displayHeadlineInput.checked
        : true;
      previewTitleEl.textContent = decodeEntities(
        showHeadline
          ? flizpayParams.checkout_preview.titleFull
          : flizpayParams.checkout_preview.titlePlain,
      );
    }

    function updatePreviewLogo() {
      if (!previewLogoEl) return;
      const showLogo = displayLogoInput ? displayLogoInput.checked : true;
      previewLogoEl.style.display = showLogo ? "" : "none";
    }

    function updatePreviewDescription() {
      if (!previewDescriptionEl) return;
      const showDescription = displayDescriptionInput
        ? displayDescriptionInput.checked
        : true;
      previewDescriptionEl.style.display = showDescription ? "" : "none";
    }

    /**
     * Preview of the on-site messages, one card per area. The cards hold real
     * <fliz-placement> elements rendered by the hosted script, so they show
     * exactly what FLIZpay currently returns for this shop. Each card follows
     * its area checkbox.
     */
    function buildPlacementPreview() {
      const isEnglish = flizpayParams.wp_locale.includes("en");
      const data = flizpayParams.placement_preview || {};
      const container = document.createElement("div");
      container.classList.add("flizpay-placement-preview");

      if (!data.publicId) {
        const hint = document.createElement("p");
        hint.classList.add("flizpay-placement-preview__hint");
        hint.textContent = isEnglish
          ? "Enable an area and save to load the preview."
          : "Aktiviere einen Bereich und speichere, um die Vorschau zu laden.";
        container.append(hint);
        return container;
      }

      const t = (en, de) => (isEnglish ? en : de);
      const price = new Intl.NumberFormat(isEnglish ? "en-GB" : "de-DE", {
        style: "currency",
        currency: data.currency || "EUR",
      }).format(data.amount / 100);

      const areas = [
        {
          setting: "product",
          caption: t("Product page", "Produktseite"),
          lines: [
            ["title", t("Example product", "Beispielprodukt")],
            ["price", price],
            ["slot", "product-price"],
            ["button", t("Add to cart", "In den Warenkorb")],
            ["slot", "product-page"],
          ],
        },
        {
          setting: "listing",
          caption: t("Product listing", "Produktliste"),
          lines: [
            ["title", t("Example product", "Beispielprodukt")],
            ["price", price],
            ["slot", "listing-item"],
            ["button", t("Add to cart", "In den Warenkorb")],
          ],
        },
        {
          setting: "cart",
          caption: t("Cart", "Warenkorb"),
          lines: [
            ["price", t("Total", "Gesamtsumme") + " " + price],
            ["slot", "cart"],
            ["button", t("Proceed to checkout", "Zur Kasse")],
          ],
        },
        {
          setting: "mini_cart",
          caption: t("Mini-cart", "Mini-Warenkorb"),
          lines: [
            ["price", t("Subtotal", "Zwischensumme") + " " + price],
            ["slot", "mini-cart"],
            ["button", t("View cart", "Warenkorb ansehen")],
          ],
        },
      ];

      areas.forEach((area) => {
        const card = document.createElement("div");
        card.classList.add("flizpay-placement-preview__card");

        const caption = document.createElement("div");
        caption.classList.add("flizpay-placement-preview__caption");
        caption.textContent = area.caption;
        card.append(caption);

        area.lines.forEach(([kind, value]) => {
          let line;
          if (kind === "slot") {
            line = document.createElement("fliz-placement");
            line.dataset.publicId = data.publicId;
            line.dataset.slot = value;
            line.dataset.locale = isEnglish ? "en" : "de";
            line.dataset.currency = data.currency || "EUR";
            line.dataset.amount = String(data.amount);
          } else {
            line = document.createElement("div");
            line.classList.add("flizpay-placement-preview__" + kind);
            line.textContent = value;
          }
          card.append(line);
        });

        const checkbox = document.querySelector(
          "#woocommerce_flizpay_flizpay_placement_" + area.setting,
        );
        const sync = () => {
          card.style.display = checkbox && !checkbox.checked ? "none" : "";
        };
        if (checkbox) jQuery(checkbox).on("change", sync);
        sync();

        container.append(card);
      });

      const note = document.createElement("p");
      note.classList.add("flizpay-placement-preview__hint");
      note.textContent = t(
        "Messages appear only while you offer a discount. Text and design are managed by FLIZpay.",
        "Hinweise erscheinen nur, solange du einen Rabatt anbietest. Text und Gestaltung steuert FLIZpay.",
      );
      container.append(note);

      return container;
    }
  });
})(jQuery);
