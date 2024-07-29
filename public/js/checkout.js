// const settings= window.wc.wcSettings.getSetting( 'woocommerce_flizpay_settings', {} );
const settings = window.wc.wcSettings.allSettings.paymentMethodData.flizpay;

const label = window.wp.htmlEntities.decodeEntities(settings.title);
const Content = () => {
  return window.wp.htmlEntities.decodeEntities(settings.description || "");
};

const Flizpay_Gateway = {
  name: "flizpay",
  label: label,
  content: Object(window.wp.element.createElement)(Content, null),
  edit: Object(window.wp.element.createElement)(Content, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: ["products"], //settings.supports,
  },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Flizpay_Gateway);
