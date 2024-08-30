(function ($) {
  "use strict";

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
  });
})(jQuery);
