const settings = window.wc.wcSettings.allSettings.paymentMethodData.flizpay;

const label = window.wp.htmlEntities.decodeEntities(settings.title);
const Content = () => {
  return window.wp.htmlEntities.decodeEntities(settings.description || "");
};
function LabelElement() {
  return React.createElement(
    "div",
    {
      style: {
        display: "flex",
        justifyContent: "space-between",
        flexWrap: "wrap",
        width: "100%",
        alignItems: "center",
      },
    },
    React.createElement("p", { style: { paddingLeft: "4px" } }, label),
    React.createElement("img", {
      width: "68",
      height: "36",
      src: "https://woocommerce-plugin-assets.s3.eu-central-1.amazonaws.com/fliz-checkout-logo.png",
    })
  );
}

const Flizpay_Gateway = {
  name: "flizpay",
  label: React.createElement(LabelElement, null),
  content: Object(window.wp.element.createElement)(Content, null),
  edit: Object(window.wp.element.createElement)(Content, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: ["products"], //settings.supports,
  },
};

if (settings.enabled) {
  window.wc.wcBlocksRegistry.registerPaymentMethod(Flizpay_Gateway);
}
