/**
 *
 * @since 1.4.0
 */

jQuery(function ($) {
  $(document).ready(function () {
    //Futura Font
    document.head.insertAdjacentHTML(
      "beforeend",
      `<link href="https://fonts.cdnfonts.com/css/br-cobane" rel="stylesheet">`
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

        const addToCartForm = document.querySelectorAll(".cart")[1] ?? document.querySelector(".cart");

        const [expressCheckoutButton, expressCheckoutContainer] =
          create_components();
        
        addToCartForm.parentNode.insertBefore(
          expressCheckoutContainer,
          addToCartForm.nextSibling
        );

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

        // Reduce font class
        expressCheckoutButton.classList.add('flizpay-express-checkout-minicart-button')

        //Render the classic mini cart button as it's always in the DOM
        if (classicMiniCartButtons) {
          classicMiniCartButtons.append(expressCheckoutContainer);
        } else {
          rebuild_minicart_button_loop(expressCheckoutContainer);
        }

      }, 1300);

      function rebuild_minicart_button_loop(expressCheckoutContainer) {
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
      }
    }

    function cart_submit(e) {
      e.preventDefault();

      submit_order({ cart: true });
    }

    function product_submit(e) {
      e.preventDefault();

      const quantity = jQuery("input.qty").val() || "1";
      const productId = jQuery('[name="add-to-cart"]').val();
      const variationId = jQuery('[name="variation_id"]').val();
      
      // Collect variation attributes if this is a variable product
      let variationData = {};
      if (variationId) {
        // Get all variation attribute fields
        jQuery('.variations select').each(function() {
          const attributeName = jQuery(this).attr('name');
          variationData[attributeName] = jQuery(this).val();
        });
      }

      if (!productId) {
        alert("Product ID not found.");
        return window.location.reload();
      }

      submit_order({ productId, quantity, variationId, variationData });
    }

    function mini_cart_submit(e) {
      e.preventDefault();

      submit_order({ cart: true });
    }

    function submit_order({
      productId,
      quantity,
      variationId = null,
      variationData = {},
      cart = false,
    }) {
      if (window.innerWidth < 768) {
        window.flizPay.fliz_block_ui({ express: true });
      } else {
        window.flizPay.flizLoadingButton();
      }
      const data = {
        action: "flizpay_express_checkout",
        product_id: productId,
        quantity: quantity,
        variation_id: variationId,
        variation_data: JSON.stringify(variationData),
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
