(function ($) {
  "use strict";

  /**
   * All of the code for your public-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   */

  $(function () {
    $(document).ready(function () {
      let paymentMethodSelector;
      let formSelector;

      if (document.querySelector(".wc-block-checkout__form")) {
        formSelector = ".wc-block-checkout__form";
        paymentMethodSelector =
          'input[name="radio-control-wc-payment-method-options"]';
      } else if (document.querySelector("form.woocommerce-checkout")) {
        formSelector = "form.woocommerce-checkout";
        paymentMethodSelector = 'input[name="payment_method"]';
      } else {
        formSelector = "form.checkout";
        paymentMethodSelector = 'input[name="payment_method"]';
      }

      $(formSelector).on("checkout_place_order_success", function (_, data) {
        const chosen_payment = $(paymentMethodSelector + ":checked").val();
        if (
          data.result === "success" &&
          data.redirect &&
          chosen_payment === "flizpay"
        ) {
          window.location.href = data.redirect;
        }
      });
    });

    const observer = new MutationObserver(function () {
      const flizpayLabel = document.querySelector(
        "#radio-control-wc-payment-method-options-flizpay__label"
      );
      // const expressCheckoutBlock = document.querySelector('.wc-block-components-express-payment__content');
      if (flizpayLabel) {
        flizpayLabel.innerHTML = `<div style="display: flex; justify-content: space-between; flex-wrap: wrap; width: 100%; align-items: center;">
          <p style='padding-left: 4px;'>${Flizpay_Gateway.label}</p>
					<img width='68' height='24' src='https://woocommerce-plugin-assets.s3.eu-central-1.amazonaws.com/fliz-checkout-logo.png' />
				</div>`;
        observer.disconnect();
      }
    });

    observer.observe(document, {
      attributes: true,
      childList: true,
      characterData: false,
      subtree: true,
    });
  });
})(jQuery);
