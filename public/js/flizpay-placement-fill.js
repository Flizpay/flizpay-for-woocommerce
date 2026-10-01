/**
 * Cart and Checkout blocks: no template hooks fire there, so the slot is added
 * through the order-meta slot-fill below the order summary.
 */
(function () {
  var config = window.flizpayPlacement;
  if (!config || !window.wc || !window.wc.blocksCheckout || !window.wp.plugins) {
    return;
  }

  var props = {};
  Object.keys(config).forEach(function (name) {
    props["data-" + name] = config[name];
  });

  var el = window.wp.element.createElement;

  window.wp.plugins.registerPlugin("flizpay-placement", {
    render: function () {
      return el(window.wc.blocksCheckout.ExperimentalOrderMeta, null, el("fliz-placement", props));
    },
    scope: "woocommerce-checkout",
  });
})();
