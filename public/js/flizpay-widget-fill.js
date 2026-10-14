/**
 * Cart and Checkout blocks: no template hooks fire there, so the slot is added
 * through the order-meta slot-fill below the order summary.
 */
(function () {
  var config = window.flizpayWidget;
  if (!config || !window.wc || !window.wc.blocksCheckout || !window.wp.plugins) {
    return;
  }

  var props = {};
  Object.keys(config).forEach(function (name) {
    props["data-" + name] = config[name];
  });

  var el = window.wp.element.createElement;

  window.wp.plugins.registerPlugin("flizpay-widget", {
    render: function () {
      return el(window.wc.blocksCheckout.ExperimentalOrderMeta, null, el("fliz-widget", props));
    },
    scope: "woocommerce-checkout",
  });
})();
