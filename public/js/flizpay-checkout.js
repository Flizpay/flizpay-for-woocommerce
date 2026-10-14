/** Checkout Blocks hosts the shared checkout text and WooCommerce's native controls. */
(function () {
  const settings = window.wc.wcSettings.getSetting("paymentMethodData", {}).flizpay;
  if (!settings || !settings.enabled) return;

  const createElement = window.wp.element.createElement;

  function amountInCents(totals) {
    if (!totals) return undefined;
    const raw = totals.total_price;
    const minorUnit = totals.currency_minor_unit;
    if (
      !((typeof raw === "string" && /^\d+$/.test(raw)) || typeof raw === "number") ||
      !Number.isSafeInteger(Number(raw)) ||
      Number(raw) < 0 ||
      !Number.isInteger(minorUnit) ||
      minorUnit < 0
    ) return undefined;

    const cents = Math.round(Number(raw) * 100 / Math.pow(10, minorUnit));
    return Number.isSafeInteger(cents) && cents >= 0 ? cents : undefined;
  }

  function CheckoutSurface({ surface }) {
    const amount = window.wp.data.useSelect((select) => {
      const totals = select(window.wc.wcBlocksData.CART_STORE_KEY).getCartTotals();
      return amountInCents(totals);
    }, []);

    return createElement(
      "fliz-checkout",
      {
        "data-public-id": settings.publicId,
        "data-surface": surface,
        "data-locale": settings.locale,
        "data-amount": amount,
      },
      surface === "title" ? "FLIZpay" : null,
    );
  }

  function Content() {
    // WooCommerce hides a lone :empty child; Shadow DOM text does not count.
    return settings.showDescription
      ? createElement("div", null, createElement(CheckoutSurface, { surface: "description" }))
      : null;
  }

  function LabelElement() {
    return createElement(
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
      settings.showHeadline
        ? createElement(CheckoutSurface, { surface: "title" })
        : "FLIZpay",
      settings.icon && createElement("img", {
        width: "68",
        height: "36",
        src: settings.icon,
        alt: "",
      }),
    );
  }

  window.wc.wcBlocksRegistry.registerPaymentMethod({
    name: "flizpay",
    label: createElement(LabelElement),
    content: createElement(Content),
    edit: createElement(Content),
    canMakePayment: ({ billingAddress }) => {
      if (!settings.restrictToGermany) return true;
      const country = billingAddress && billingAddress.country;
      return !country || country === "DE";
    },
    ariaLabel: "FLIZpay",
    supports: { features: ["products"] },
  });
})();
