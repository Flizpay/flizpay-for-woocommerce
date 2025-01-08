jQuery(function ($) {
  $(document).ready(function () {
    window.flizPay = {};
    window.flizPay.stopPolling =
      localStorage.getItem("flizpay_stop_polling") || false;
    window.flizPay.refreshButtonLabel = navigator.language.includes("en")
      ? "Didn't redirect? Click here to refresh."
      : "Nicht weitergeleitet? Klicken Sie hier, um die Seite neu zu laden";
    window.flizPay.cancelButtonLabel = navigator.language.includes("en")
      ? "Or cancel."
      : "Oder Abbrechen.";
    window.flizPay.waitLabel = navigator.language.includes("en")
      ? "Wait..."
      : "Warten...";
    window.flizPay.FLIZ_LOADING_HTML = document.createElement("div");
    window.flizPay.FLIZ_LOGO = document.createElement("img");
    window.flizPay.FLIZ_LOADING_WHEEL = document.createElement("img");
    window.flizPay.FLIZ_CANCEL_BUTTON = document.createElement("button");
    window.flizPay.FLIZ_REFRESH_BUTTON = document.createElement("button");

    window.flizPay.FLIZ_LOGO.setAttribute("src", flizpay_frontend.fliz_logo);
    window.flizPay.FLIZ_LOGO.setAttribute("style", "margin-top: -10px;");
    window.flizPay.FLIZ_LOADING_WHEEL.setAttribute(
      "src",
      flizpay_frontend.fliz_loading_wheel
    );
    window.flizPay.FLIZ_LOADING_HTML.classList.add("fliz-loading-html");
    window.flizPay.FLIZ_CANCEL_BUTTON.setAttribute(
      "id",
      "flizpay-cancel-button"
    );
    window.flizPay.FLIZ_REFRESH_BUTTON.setAttribute(
      "id",
      "flizpay-refresh-button"
    );
    window.flizPay.FLIZ_CANCEL_BUTTON.classList.add("fliz-cancel-button");
    window.flizPay.FLIZ_REFRESH_BUTTON.classList.add("fliz-refresh-button");

    window.flizPay.FLIZ_CANCEL_BUTTON.append(window.flizPay.cancelButtonLabel);
    window.flizPay.FLIZ_REFRESH_BUTTON.append(window.flizPay.refreshButtonLabel);
    window.flizPay.FLIZ_LOADING_HTML.append(window.flizPay.FLIZ_LOGO);
    window.flizPay.FLIZ_LOADING_HTML.append(window.flizPay.FLIZ_LOADING_WHEEL);
    window.flizPay.FLIZ_LOADING_HTML.append(window.flizPay.FLIZ_REFRESH_BUTTON);
    window.flizPay.FLIZ_LOADING_HTML.append(window.flizPay.FLIZ_CANCEL_BUTTON);

    window.flizPay.fliz_block_ui = function fliz_block_ui(options) {
      setTimeout(
        () => {
          jQuery.blockUI({
            bindEvents: false,
            onBlock: () => {
              const flizCancelButton = document.querySelector(
                "#flizpay-cancel-button"
              );
              const flizRefreshButton = document.querySelector(
                "#flizpay-refresh-button"
              );
              flizRefreshButton.setAttribute("style", "display: none;");
              flizCancelButton.onclick = () => {
                flizCancelButton.innerHTML = window.flizPay.waitLabel;
                window.location.reload();
              };
              flizRefreshButton.onclick = () => {
                flizRefreshButton.innerHTML = window.flizPay.waitLabel;
                window.location.reload();
              }
              setTimeout(() => {
                flizRefreshButton.setAttribute("style", "display: block;");
              });
            },
            css: {
              border: "none",
              borderRadius: "8px",
              padding: "12px",
              top: "30%",
              left: "20%",
              width: "60%",
            },
            message: window.flizPay.FLIZ_LOADING_HTML,
            overlayCSS: {
              background: "#000",
              opacity: 0.6,
              cursor: "wait",
            },
          });
        },
        options?.express ? 100 : 2700
      );
    };

    window.flizPay.mobile_redirect_when_order_finished =
      function mobile_redirect_when_order_finished(order_id) {
        if (window.flizPay.stopPolling) {
          jQuery.unblockUI();
          localStorage.removeItem("flizpay_stop_polling");
          localStorage.removeItem("flizpay_order_id");
          return;
        }

        console.log("polling");
        const data = {
          action: "flizpay_order_finish",
          order_id: order_id,
          nonce: flizpay_frontend.order_finish_nonce,
        };

        jQuery.ajax({
          url: flizpay_frontend.ajaxurl,
          type: "POST",
          data,
          success: function (response) {
            setTimeout(function () {
              let order = null;
              try {
                order = JSON.parse(response);
              } catch (err) {
                console.log(err);
                order = response;
              }
              if (
                order.status === "pending" ||
                order.status === "checkout-draft"
              ) {
                window.flizPay.mobile_redirect_when_order_finished(order_id);
              } else {
                localStorage.setItem("flizpay_stop_polling", true);
                window.location.href = order.url;
              }
            }, 2000);
          },
        });
      };

    window.flizPay.updateLocalStorage = function updateLocalStorage(order_id) {
      localStorage.removeItem("flizpay_order_id");
      localStorage.setItem("flizpay_order_id", order_id);
    };
  });
});
