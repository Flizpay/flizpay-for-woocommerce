/**
 * This JS snipped is uniquely responsible for handling the redirect to the success/failure page after the payment on mobile devices.
 * The FLIZ Checkout page is also a universal link that opens the FLIZ App straight away.
 * So for mobile devices the customer remains in the page on their browsers after clicking to pay and being redirected to the app.
 * This JS implements a simple loading screen compatible with blocks and classic checkout.
 * By doing a polling of the order status via ajax, it will then know when the webhook communication happened and redirect the customer accordingly.
 *
 * @since 1.0.0
 */

jQuery(function ($) {
  $(document).ready(function () {
    try {
      setTimeout(() => {
        const isBLocks = document.querySelector("form.wc-block-checkout__form");
        const isClassic = document.querySelector("form.woocommerce-checkout");
        const existentOrderId = localStorage.getItem("flizpay_order_id");

        if (isBLocks) bind_blocks_hook();
        if (isClassic) bind_ajax_hook(isClassic);

        if (existentOrderId)
          window.flizPay.mobile_redirect_when_order_finished(existentOrderId);
      }, 1700);
    } catch (err) {
      console.log(err);
    }

    function bind_blocks_hook() {
      const unsubscribe = wp.data.subscribe(() => {
        const checkoutStatus = wp.data
          .select("wc/store/checkout")
          .getCheckoutStatus();
        if (
          checkoutStatus === "complete" &&
          document.querySelector(
            "#radio-control-wc-payment-method-options-flizpay"
          ).checked === true
        ) {
          window.flizPay.fliz_block_ui();
          window.flizPay.mobile_redirect_when_order_finished(
            wp.data.select("wc/store/checkout").getOrderId()
          );
          window.flizPay.updateLocalStorage(
            wp.data.select("wc/store/checkout").getOrderId()
          );
          unsubscribe();
        }
      });
    }

    function bind_ajax_hook(isClassic) {
      jQuery(document.body).on("checkout_error", () => {
        jQuery.unblockUI();
        window.flizPay.stopPolling = true;
      });

      jQuery(isClassic).on("checkout_place_order_flizpay", () => {
        jQuery(document).ajaxComplete(function (_, xhr, settings) {
          if (settings.url.indexOf("wc-ajax=checkout") !== -1) {
            const response = xhr.responseJSON;
            if (response && response.result === "success") {
              if (response.order_id) {
                window.flizPay.stopPolling = false;
                window.flizPay.fliz_block_ui();
                window.flizPay.mobile_redirect_when_order_finished(
                  response.order_id
                );
                window.flizPay.updateLocalStorage(response.order_id);
              }
            }
          }
        });
      });
    }
  });
});
