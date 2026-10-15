/** Mount checkout text after WooCommerce renders or replaces its payment fragment. */
jQuery(function ($) {
  function bindCheckout() {
    const method = document.querySelector(".wc_payment_method.payment_method_flizpay");
    const context = method && method.querySelector(".flizpay-checkout-context");
    const label = method && method.querySelector('label[for="payment_method_flizpay"]');
    if (!context || !label) return;

    function updateSurface(element, surface) {
      element.dataset.surface = surface;
      element.dataset.locale = context.dataset.locale;
      if (context.hasAttribute("data-amount")) {
        element.dataset.amount = context.dataset.amount;
      } else {
        element.removeAttribute("data-amount");
      }
      element.dataset.publicId = context.dataset.publicId;
    }

    let title = label.querySelector('fliz-checkout[data-surface="title"]');
    if (context.dataset.showHeadline === "yes") {
      if (!title) {
        // Replace only the native name, including when a theme wraps it in a span.
        // The radio, logo, and other theme markup keep their original nodes.
        const text = document.createTreeWalker(label, NodeFilter.SHOW_TEXT);
        let name;
        while ((name = text.nextNode())) {
          if (name.textContent.trim() !== "FLIZpay") continue;
          title = document.createElement("fliz-checkout");
          title.textContent = "FLIZpay";
          updateSurface(title, "title");
          name.replaceWith(title);
          break;
        }
      } else {
        updateSurface(title, "title");
      }
    } else if (title) {
      title.replaceWith(document.createTextNode("FLIZpay"));
    }

    const container = method.querySelector(".flizpay-checkout-description");
    if (!container) return;
    let description = container.querySelector("fliz-checkout");
    if (context.dataset.showDescription === "yes") {
      if (!description) {
        description = document.createElement("fliz-checkout");
        updateSurface(description, "description");
        container.append(description);
      } else {
        updateSurface(description, "description");
      }
    } else {
      container.replaceChildren();
    }
  }

  $(document.body)
    .off("updated_checkout.flizpayCheckout")
    .on("updated_checkout.flizpayCheckout", bindCheckout);
  bindCheckout();
});
