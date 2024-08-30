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
          mobile_redirect_when_order_finished(data.order_id);
        }
      });
    });

    function mobile_redirect_when_order_finished(order_id) {
      const data = {
        action: "flizpay_order_finish",
        order_id: order_id,
        nonce: flizpay_frontend.order_finish_nonce,
      };

      jQuery.ajax({
        url: flizpay_frontend.ajaxurl,
        type: "POST",
        data,
        success: function (response) {
          window.setTimeout(function () {
            let order = null;
            try {
              order = JSON.parse(response);
            } catch (err) {
              console.log(err);
              order = response;
            }
            if (order.status == "pending") {
              mobile_redirect_when_order_finished(order_id);
            } else {
              window.location.href = order.url;
            }
          }, 2000);
        },
      });
    }
  });
})(jQuery);
