/**
 *
 * @since 1.4.0
 */

jQuery(function ($) {
  $(document).ready(function () {
    document.head.insertAdjacentHTML(
      "beforeend",
      `<link href="https://fonts.googleapis.com/css2?family=Google+Sans&display=swap" rel="stylesheet">`
    );
    function is_express_checkout() {
      if (!flizpay_frontend.enable_express_checkout) return false;

      if (flizpay_frontend.is_cart)
        return flizpay_frontend.express_checkout_pages.includes(
          flizpay_frontend.cart_page_index
        );

      if (flizpay_frontend.is_product)
        return flizpay_frontend.express_checkout_pages.includes(
          flizpay_frontend.product_page_index
        );

      return false;
    }

    function should_render_mini_cart_button() {
      return (
        flizpay_frontend.enable_express_checkout &&
        flizpay_frontend.express_checkout_pages.includes(
          flizpay_frontend.cart_page_index
        )
      );
    }

    function create_components() {
      const expressCheckoutContainer = document.createElement("div");
      const expressCheckoutButton = document.createElement("button");
      const expressCheckoutIcon = document.createElement("img");

      expressCheckoutContainer.classList.add(
        "flizpay-express-checkout-container-checkout"
      );
      expressCheckoutButton.classList.add("button");
      expressCheckoutButton.classList.add("flizpay-express-checkout-button");
      expressCheckoutButton.classList.add(
        `flizpay-express-checkout-${flizpay_frontend.express_checkout_theme}`
      );
      expressCheckoutIcon.setAttribute(
        "src",
        flizpay_frontend[flizpay_frontend.express_checkout_theme + "_icon"]
      );
      expressCheckoutIcon.setAttribute("width", "115");
      expressCheckoutIcon.setAttribute("height", "29");
      expressCheckoutButton.append(expressCheckoutIcon);
      expressCheckoutButton.append(flizpay_frontend.express_checkout_title);
      expressCheckoutContainer.append(expressCheckoutButton);

      return [expressCheckoutButton, expressCheckoutContainer];
    }

    function render_product_button() {
      setTimeout(() => {
        const addToCartButton = document.querySelector(
          ".single_add_to_cart_button"
        );

        if (!addToCartButton) return;

        const addToCartForm = document.querySelector(".cart");

        const [expressCheckoutButton, expressCheckoutContainer] =
          create_components();
        if (addToCartButton.classList.contains("disabled")) {
          expressCheckoutButton.classList.add("disabled");
        } else {
          expressCheckoutButton.addEventListener("click", product_submit);
        }

        const addToCartObserver = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (mutation.attributeName === "class") {
              if (addToCartButton.classList.contains("disabled")) {
                expressCheckoutButton.classList.add("disabled");
                expressCheckoutButton.removeEventListener(
                  "click",
                  product_submit
                );
              } else {
                expressCheckoutButton.addEventListener("click", product_submit);
                expressCheckoutButton.classList.remove("disabled");
              }
            }
          });
        });

        // Start observing the button for class changes
        addToCartObserver.observe(addToCartButton, {
          attributes: true,
          attributeFilter: ["class"],
        });

        addToCartForm.parentNode.insertBefore(
          expressCheckoutContainer,
          addToCartForm.nextSibling
        );
      }, 1700);
    }

    function render_cart_button() {
      setTimeout(() => {
        const [expressCheckoutButton, expressCheckoutContainer] =
          create_components();
        const classicProceedToCheckoutButton =
          document.querySelector(".checkout-button");

        const blocksPaymentMethodsSection = document.querySelector(
          ".wc-block-cart__payment-options"
        );

        const proceedToCheckoutBlock = document.querySelector(
          ".wp-block-woocommerce-proceed-to-checkout-block"
        );
        expressCheckoutButton.addEventListener("click", cart_submit);

        if (classicProceedToCheckoutButton) {
          classicProceedToCheckoutButton.parentNode.insertBefore(
            expressCheckoutContainer,
            classicProceedToCheckoutButton.nextSibling
          );

          return;
        }

        if (blocksPaymentMethodsSection) {
          blocksPaymentMethodsSection.prepend(expressCheckoutContainer);
        } else {
          proceedToCheckoutBlock.append(expressCheckoutContainer);
        }
      }, 1700);
    }

    function render_mini_cart_button() {
      setTimeout(() => {
        const [expressCheckoutButton, expressCheckoutContainer] =
          create_components();
        const classicMiniCartButtons = document.querySelector(
          ".woocommerce-mini-cart"
        );
        // Attach click event to the express checkout button
        expressCheckoutButton.addEventListener("click", mini_cart_submit);
        //Render the classic mini cart button as it's always in the DOM
        if (classicMiniCartButtons) {
          classicMiniCartButtons.append(expressCheckoutContainer);
        }

        // Function to observe changes in the mini cart and add the button when it appears
        setInterval(() => {
          const blocksMiniCartFooter = document.querySelector(
            ".wc-block-mini-cart__footer-actions"
          );
          const blocksMiniCartButton = document.querySelector(
            ".wp-block-woocommerce-filled-mini-cart-contents-block .flizpay-express-checkout-container-checkout"
          );
              // Check if mini cart elements are now available
          if (blocksMiniCartFooter && !blocksMiniCartButton) {
            blocksMiniCartFooter.parentNode.insertBefore(
              expressCheckoutContainer,
              blocksMiniCartFooter.nextSibling
            );
            
          }
        }, 650);
      }, 1300);
    }

    function append_info_to_loading() {
      if (document.querySelector("#flizpay-thanks-info")) return;

      const thanks = document.createElement("p");
      const info = document.createElement("p");
      thanks.setAttribute("id", "flizpay-thanks-info");
      thanks.classList.add("fliz-black-text");
      thanks.classList.add("fliz-highlight-text");
      info.classList.add("fliz-black-text");
      thanks.append(
        navigator.language.includes("en")
          ? "Thank you for choosing FLIZpay!"
          : "Danke, dass Sie sich für FLIZpay entschieden haben!"
      );
      info.append(
        navigator.language.includes("en")
          ? "Redirecting to our secure environment..."
          : "Weiterleitung zu unserer sicheren Umgebung..."
      );
      window.flizPay.FLIZ_CANCEL_BUTTON.parentNode.insertBefore(
        info,
        window.flizPay.FLIZ_CANCEL_BUTTON
      );
      info.parentNode.insertBefore(thanks, info);
    }

    function cart_submit(e) {
      e.preventDefault();

      //append_info_to_loading();
      window.flizPay.fliz_block_ui({ express: true });
      submit_order({ cart: true });
    }

    function product_submit(e) {
      e.preventDefault();

      //append_info_to_loading();
      window.flizPay.fliz_block_ui({ express: true });

      const quantity = jQuery("input.qty").val() || "1";
      const productId = jQuery('[name="add-to-cart"]').val();
      const variationId = jQuery('[name="variation_id"]').val();

      if (!productId) {
        alert("Product ID not found.");
        return window.location.reload();
      }

      submit_order({ productId, quantity, variationId });
    }

    function mini_cart_submit(e) {
      e.preventDefault();

      //append_info_to_loading();
      window.flizPay.fliz_block_ui({ express: true });
      submit_order({ cart: true });
    }

    function submit_order({
      productId,
      quantity,
      variationId = null,
      cart = false,
    }) {
      const data = {
        action: "flizpay_express_checkout",
        product_id: productId,
        quantity: quantity,
        variation_id: variationId,
        nonce: flizpay_frontend.express_checkout_nonce, // For security
      };
      if (cart) data.cart = true;

      jQuery.ajax({
        url: flizpay_frontend.ajaxurl, // Provided by wp_localize_script
        type: "POST",
        data,
        beforeSend: function () {
          // Optional: Show a loading spinner
          jQuery(".flizpay-express-checkout-button").prop("disabled", true);
        },
        success: function (response) {
          if (response.success && response.data.result === "success") {
            window.flizPay.updateLocalStorage(response.data.order_id);
            window.flizPay.mobile_redirect_when_order_finished(
              response.data.order_id
            );
            window.flizPay.stopPolling = false;
            // Redirect to checkout page
            window.location.href = response.data.redirect;
          } else {
            alert(response.data?.message || "Error.");
            jQuery.unblockUI();
            window.location.reload();
          }
        },
        complete: function () {
          jQuery(".flizpay-express-checkout-button").prop("disabled", false);
        },
        error: function (_, status, error) {
          console.error("AJAX Error:", status, error);
          jQuery.unblockUI();
          alert(error);
          window.location.reload();
        },
      });
    }

    if (should_render_mini_cart_button()) render_mini_cart_button();

    if (is_express_checkout()) {
      if (flizpay_frontend.is_cart) render_cart_button();
      if (flizpay_frontend.is_product) render_product_button();
    }
  });
});
