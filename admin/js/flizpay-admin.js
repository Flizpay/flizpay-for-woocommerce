(function ($) {
  "use strict";

  /**
   * All of the code for your admin-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   *
   * $(function() {
   *
   * });
   *
   * When the window is loaded:
   *
   * $( window ).load(function() {
   *
   * });
   *
   * ...and/or other possibilities.
   *
   * Ideally, it is not considered best practise to attach more than a
   * single DOM-ready or window-load handler for a particular page.
   * Although scripts in the WordPress core, Plugins and Themes may be
   * practising this, we should strive to set a better example in our own work.
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
    const titleInput = document.querySelector("#woocommerce_flizpay_title");
    const descriptionInput = document.querySelector(
      "#woocommerce_flizpay_description"
    );

    testButton.setAttribute("id", "woocommerce_flizpay_test_connection");
    resultField.setAttribute("id", "woocommerce_flizpay_connection_result");
    apiKeyInput.parentNode.appendChild(testButton);
    apiKeyInput.parentNode.appendChild(resultField);
    testButton.innerHTML = `<button id="test_connection_button" type="button">${
      webhookAlive.getAttribute("checked") ? "Reconfigure" : "Configure"
    } Connection</button>`;
    webhookURLInput.setAttribute("disabled", true);
    enabledCheckbox.setAttribute("disabled", true);
    webhookAlive.setAttribute("disabled", true);
    titleInput.setAttribute("disabled", true);
    descriptionInput.setAttribute("disabled", true);
    apiKeyInput.setAttribute("type", "password");

    if (webhookAlive.getAttribute("checked")) {
      const description =
        webhookAlive.parentElement.nextElementSibling.nextElementSibling;
      description.setAttribute(
        "style",
        "color: white; background-color: green; padding: 10px; font-weight: bold;"
      );
      description.innerHTML =
        "Our servers communicated successfully with your site. You're ready to go free of charges!";
    }

    $("#test_connection_button").on("click", function () {
      let proceed;

      if (webhookURLInput.value.length !== 0) {
        proceed = confirm(
          "Looks like you already have an integration settled up. By reconfiguring the integration you will invalidate all current ongoing payment responses. Proceed?"
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
          success: function (response) {
            if (response.success) {
              resultField.classList.add("connection-success");
              testButton.classList.add("hidden");
              apiKeyInput.setAttribute("disabled", "true");
              resultField.innerHTML =
                "Connected! Waiting for the webhook confirmation.";
              webhookURLInput.value = response.data.webhookUrl;
            } else {
              resultField.classList.add("connection-failed");
              resultField.innerHTML = response.data;
            }
          },
          error: function (e) {
            resultField.classList.add("connection-failed");
            resultField.innerHTML =
              "An error occurred while testing the connection. " +
              JSON.stringify(e);
          },
        });
      }
    });
  });
})(jQuery);
