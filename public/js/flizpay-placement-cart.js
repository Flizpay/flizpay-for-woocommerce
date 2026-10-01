/**
 * Cart block: the classic woocommerce_proceed_to_checkout hook does not fire here,
 * so the cart placement is added through the order-meta slot below the totals.
 */
(function () {
  var config = window.flizpayPlacement;
  if (!config || !window.wc || !window.wc.blocksCheckout || !window.wp.plugins) {
    return;
  }

  var el = window.wp.element.createElement;

  window.wp.plugins.registerPlugin("flizpay-cart-placement", {
    render: function () {
      return el(
        window.wc.blocksCheckout.ExperimentalOrderMeta,
        null,
        el("fliz-placement", {
          "data-public-id": config["public-id"],
          "data-slot": config.slot,
          "data-locale": config.locale,
        })
      );
    },
    scope: "woocommerce-checkout",
  });
})();
