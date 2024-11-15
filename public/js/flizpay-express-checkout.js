/**
 *
 * @since 1.4.0
 */

jQuery(function ($) {
  $(document).ready(function () {
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
      expressCheckoutButton.append(expressCheckoutIcon);
      expressCheckoutButton.append(flizpay_frontend.express_checkout_title);
      expressCheckoutContainer.append(expressCheckoutButton);

      return [expressCheckoutButton, expressCheckoutContainer];
    }

    function render_product_button() {
      const addToCartForm = document.querySelector(".cart");
      const [expressCheckoutButton, expressCheckoutContainer] =
        create_components();
      expressCheckoutButton.addEventListener("click", product_submit);
      addToCartForm.parentNode.insertBefore(
        expressCheckoutContainer,
        addToCartForm.nextSibling
      );
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
      }, 1000);
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
        const miniCartObserver = new MutationObserver((mutations) => {
          mutations.forEach((mutation) => {
            if (mutation.addedNodes.length > 0) {
              const blocksMiniCartFooter = document.querySelector(
                ".wc-block-mini-cart__footer-actions"
              );
              // Check if mini cart elements are now available
              if (blocksMiniCartFooter) {
                blocksMiniCartFooter.parentNode.insertBefore(
                  expressCheckoutContainer,
                  blocksMiniCartFooter.nextSibling
                );
                miniCartObserver.disconnect(); // Stop observing once the button is added
              }
            }
          });
        });

        // Start observing the document body for changes
        miniCartObserver.observe(document.body, {
          childList: true,
          subtree: true,
        });
      }, 1000);
    }

    function cart_submit() {
      alert("cart_submit");
    }

    function product_submit() {
      alert("product_submit");
    }

    function mini_cart_submit() {
      alert("mini cart submit");
    }

    if (should_render_mini_cart_button()) render_mini_cart_button();

    if (is_express_checkout()) {
      if (flizpay_frontend.is_cart) render_cart_button();
      if (flizpay_frontend.is_product) render_product_button();
    }
  });
});
