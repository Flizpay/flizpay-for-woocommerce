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

    function is_cart_empty() {
      // Check for empty cart message
      const emptyCartMessage = document.querySelector('.cart-empty.woocommerce-info');
      if (emptyCartMessage) return true;
      
      // Check for cart items - standard WooCommerce
      const cartItems = document.querySelector('.woocommerce-cart-form__cart-item');
      if (cartItems) return false;
      
      // Check for cart items - WooCommerce Blocks
      const blockCartItems = document.querySelector('.wc-block-cart-items__row');
      if (blockCartItems) return false;
      
      // Check if we have a cart form but no checkout button
      const cartForm = document.querySelector('.woocommerce-cart-form');
      const checkoutButton = document.querySelector('.checkout-button');
      if (cartForm && (!checkoutButton || checkoutButton.classList.contains('disabled'))) return true;
      
      // Default based on cart form presence
      return !cartForm && !document.querySelector('.wc-block-cart__items');
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
        
        // Check if cart is empty and disable button if it is
        if (is_cart_empty()) {
          expressCheckoutButton.classList.add('disabled');
          expressCheckoutButton.style.pointerEvents = 'none';
          expressCheckoutButton.style.cursor = 'not-allowed';
        } else {
          expressCheckoutButton.addEventListener("click", cart_submit);
        }

        // Simple function to update button state
        const updateCartButton = () => {
          const isEmpty = is_cart_empty();
          if (isEmpty) {
            expressCheckoutButton.classList.add('disabled');
            expressCheckoutButton.style.pointerEvents = 'none';
            expressCheckoutButton.style.cursor = 'not-allowed';
            expressCheckoutButton.removeEventListener("click", cart_submit);
          } else {
            expressCheckoutButton.classList.remove('disabled');
            expressCheckoutButton.style.pointerEvents = '';
            expressCheckoutButton.style.cursor = '';
            // Remove and reattach to prevent duplicate handlers
            expressCheckoutButton.removeEventListener("click", cart_submit);
            expressCheckoutButton.addEventListener("click", cart_submit);
          }
        };
        
        // Listen for standard WooCommerce cart update events
        jQuery(document.body).on('updated_cart_totals updated_wc_div added_to_cart removed_from_cart', updateCartButton);
        
        // Setup a single mutation observer on the cart container
        const cartContainer = document.querySelector('.woocommerce-cart-form, .wc-block-cart');
        if (cartContainer) {
          const cartObserver = new MutationObserver(updateCartButton);
          cartObserver.observe(cartContainer, { 
            childList: true, 
            subtree: true 
          });
        }

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
        
        // CRITICAL: Always attach the click handler first
        expressCheckoutButton.addEventListener("click", mini_cart_submit);
        
        // Reduce font class
        expressCheckoutButton.classList.add('flizpay-express-checkout-minicart-button');

        // Attach click handler and FORCE ENABLE the button by default
        expressCheckoutButton.classList.remove('disabled');
        expressCheckoutButton.style.pointerEvents = '';
        expressCheckoutButton.style.cursor = '';

        //Render the classic mini cart button as it's always in the DOM
        if (classicMiniCartButtons) {
          // ORIGINAL APPROACH - WORKING IN MOST THEMES
          classicMiniCartButtons.append(expressCheckoutContainer);
        } else {
          // FIND THE BLOCKS MINI CART
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
