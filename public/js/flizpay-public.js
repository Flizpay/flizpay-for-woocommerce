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
      const initFlizpayObserver = new MutationObserver(function () {
        const settings =
          window.wc.wcSettings.allSettings.paymentMethodData.flizpay;
        if (document.querySelector(".wc-block-checkout__form")) {
          const form = "form.wc-block-checkout__form",
            placeOrderButton =
              ".wc-block-components-checkout-place-order-button",
            paymentMethodSelector =
              'input[name="radio-control-wc-payment-method-options"]',
            is_block = "yes";

          setTimeout(
            () =>
              initilize_flizpay_payment_process(
                form,
                placeOrderButton,
                paymentMethodSelector,
                is_block
              ),
            1500
          );
        } else {
          const form = "form.woocommerce-checkout",
            placeOrderButton =
              'button[type="submit"][name="woocommerce_checkout_place_order"]',
            paymentMethodSelector = 'input[name="payment_method"]',
            is_block = "no";
          setTimeout(
            () =>
              initilize_flizpay_payment_process(
                form,
                placeOrderButton,
                paymentMethodSelector,
                is_block
              ),
            1500
          );
        }
      });

      initFlizpayObserver.observe(document, {
        attributes: false,
        childList: true,
        characterData: false,
        subtree: true,
      });
      /**
	 * When the window is loaded:
	 *
		$( window ).load(function() {
			// initilize_flizpay_payment_process()
		});
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

      function initilize_flizpay_payment_process(
        form,
        placeOrderButton,
        paymentMethodSelector,
        is_block
      ) {
        $(form).on("click", placeOrderButton, function (e) {
          const chosen_payment = $(paymentMethodSelector + ":checked").val();
          if (chosen_payment === "flizpay") {
            e.preventDefault();
            e.stopImmediatePropagation();

            const validation = validateForm(form);

            if (validation === "pass") {
              if (is_block === "yes") {
                $(this).addClass("wc-block-components-button--loading");
                $(".wc-block-components-button__text").before(
                  '<span class="wc-block-components-spinner" aria-hidden="true"></span>'
                );
              } else {
                $(this).text("Please Wait...");
              }

              get_order_data(chosen_payment);
            } else {
              jQuery(form).off();
              initilize_flizpay_payment_process();
            }
          } else {
            return;
          }
        });
      }

      /**
       * @param {String} chosen_payment
       */
      function get_order_data(chosen_payment) {
        $(document.body).trigger("update_checkout");

        const data = {
          action: "flizpay_get_payment_data",
        };

        jQuery.ajax({
          url: flizpay_frontend.ajaxurl,
          type: "POST",
          data,
          success: function (response) {
            load_flizpay_modal(chosen_payment, response);
          },
          error: function (error) {
            console.log(error);
            $(".wc-block-components-spinner").remove();
          },
        });
      }

      /**
       *
       * @param {String} chosen_payment
       * @param {JSON} json
       */
      function load_flizpay_modal(chosen_payment, json) {
        const returned_data = JSON.parse(json);
        if (
          $(".confirmation-modal").length === 0 &&
          chosen_payment === "flizpay"
        ) {
          openModalWithIframe(
            returned_data["callback_url"],
            returned_data["order_id"]
          );
        }
      }

      /**
       *
       * @param {String} url
       * @param {String} order_id
       */

      function openModalWithIframe(url, order_id) {
        // Create the modal container
        const modal = $(`<div class="confirmation-modal"></div>`);
        const modalContent = $(`<div class="modal-content"></div>`);
        const flizpayContainer = $(`<div class="flizpay-container"></div>`);

        modalContent.append(flizpayContainer);
        modal.append(modalContent);

        // Create the iframe element
        const iframe = $("<iframe></iframe>").attr("src", url).css({
          width: "100%",
          height: "100%",
          border: "none",
          "border-radius": "10px",
        });

        // Append the iframe to the modal
        flizpayContainer.append(iframe);

        // Append the modal to the body
        $("body").append(modal);

        // Close modal on click outside iframe
        modal.on("click", function (e) {
          if (e.target === this) {
            $(this).remove();
            $(".wc-block-components-spinner").remove();
          }
        });

        flizpay_load_order_finish_page(order_id);
      }

      /**
       *
       * @param {String} order_id
       */
      function flizpay_load_order_finish_page(order_id) {
        const data = {
          action: "flizpay_order_finish",
          order_id: order_id,
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
                flizpay_load_order_finish_page(order_id);
              } else {
                window.location.href = order.url;
              }
            }, 2000);
          },
        });
      }

      function validateForm(form) {
        let validation = "pass";
        $(form + " input")
          .not(":hidden")
          .each(function () {
            if (!`#${$(this).attr("id")}_field`.includes(":"))
              try {
                const currentInput = $(this);
                const closestInput = $(this)
                  .parent()
                  .closest(`#${$(this).attr("id")}_field`);
                if (
                  (currentInput.is("[required]") ||
                    closestInput.hasClass("validate-required")) &&
                  currentInput.val().length === 0
                ) {
                  currentInput.parent().addClass("has-error");
                  validation = "fail";
                }
              } catch (e) {
                console.log(e);
                validation = "pass";
              }
          });

        return validation;
      }
    });

    // Hide payment fail menu item
    $("ul li a").each(function (_, item) {
      if (item.text === "Flizpay Payment Fail") {
        $(this).closest("li").remove();
      }
    });

    const observer = new MutationObserver(function () {
      const flizpayLabel = document.querySelector(
        "#radio-control-wc-payment-method-options-flizpay__label"
      );
      // const expressCheckoutBlock = document.querySelector('.wc-block-components-express-payment__content');
      if (flizpayLabel) {
        flizpayLabel.innerHTML = `
					<image src='https://woocommerce-plugin-assets.s3.eu-central-1.amazonaws.com/fliz-checkout-logo.png' />
					<p style='padding-left: 4px;'>${settings.title.split("-")[1] ?? ""}</p>
				`;
        observer.disconnect();
      }
    });

    observer.observe(document, {
      attributes: false,
      childList: true,
      characterData: false,
      subtree: true,
    });
  });
})(jQuery);
