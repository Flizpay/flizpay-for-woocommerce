(function ($) {
  "use strict";

  /**
   * This JS snippet is responsible for customizing the styles of the FLIZ Settings page.
   * It will also be handling the saving of the settings by calling, via ajax, the test connection method of the Gateway Class
   *
   * @since 1.0.0
   */
  jQuery(document).ready(function ($) {
    const testButton = document.createElement("div");
    const resultField = document.createElement("div");
    const apiKeyInput = document.querySelector(
      "#woocommerce_flizpay_flizpay_api_key"
    );
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

    testButton.setAttribute("id", "woocommerce_flizpay_test_connection");
    resultField.setAttribute("id", "woocommerce_flizpay_connection_result");
    apiKeyInput.parentNode.appendChild(testButton);
    apiKeyInput.parentNode.appendChild(resultField);
    webhookURLInput.setAttribute("disabled", true);
    webhookURLInput.setAttribute("type", "hidden");
    enabledCheckbox.setAttribute("disabled", true);
    webhookAlive.setAttribute("disabled", true);

    if (webhookAlive.getAttribute("checked")) {
      description.setAttribute(
        "style",
        "color: white; background-color: green; padding: 10px; font-weight: bold; margin-top: 30px;"
      );
      description.innerHTML = `Unsere Server haben erfolgreich mit deiner Website kommuniziert. Du kannst jetzt gebührenfreie Zahlungen erhalten!<br>
      <p style='font-style: italic;'>Our servers have successfully communicated with your site. You're now ready to accept fee-free payments!</p>`;
    }

    $(".woocommerce-save-button").on("click", function (e) {
      e.preventDefault();
      e.stopImmediatePropagation();

      let proceed;

      if (webhookURLInput.value.length !== 0) {
        proceed = confirm(
          `Sieht so aus, als ob Sie bereits eine Integration eingerichtet haben. Durch die Neukonfiguration der Integration machen Sie alle aktuellen laufenden Zahlungsantworten ungültig. Fortfahren?\n\n
        Looks like you already have an integration settled up. By reconfiguring the integration you will invalidate all current ongoing payment responses. Proceed?`
        );
      } else {
        proceed = true;
      }

      if (proceed) {
        var nonce = flizpayParams.nonce;

        $.ajax({
          url: ajaxurl,
          method: "POST",
          data: {
            action: "test_gateway_connection",
            api_key: apiKeyInput.value,
            nonce: nonce,
          },
          success: async function (response) {
            if (response.success) {
              resultField.classList.add("connection-success");
              testButton.classList.add("hidden");
              apiKeyInput.setAttribute("disabled", "true");
              resultField.innerHTML = `<p>Verbunden! Warte auf die Webhook-Bestätigung. <br />
              Die Seite wird in 5 Sekunden automatisch neu geladen ...<p>
              <p style="font-style: italic;">Connected! Waiting for the webhook confirmation. <br />
              Page will reload automatically in 5 seconds...</p>
              <image src='/wp-content/plugins/flizpay-for-woocommerce/assets/images/loading.svg' />`;
              webhookURLInput.value = response.data.webhookUrl;
              setTimeout(() => {
                $("form").submit();
              }, 5500);
            } else {
              resultField.classList.add("connection-failed");
              resultField.innerHTML = `An error occurred while testing the connection. <br />
              ${response.data} <br />
              <image src='/wp-content/plugins/flizpay-for-woocommerce/assets/images/loading.svg' />`;
            }
          },
          error: async function (e) {
            resultField.classList.add("connection-failed");
            resultField.innerHTML = `An error occurred while testing the connection. <br />
              ${e.responseJSON.data} <br />
              <image src='/wp-content/plugins/flizpay-for-woocommerce/assets/images/loading.svg' />`;
          },
        });
      }
    });
  });
})(jQuery);
