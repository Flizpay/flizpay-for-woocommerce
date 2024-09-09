jQuery(function ($) {
  $(document).ready(function () {
    let stopPolling = false;
    try {
      const FLIZ_LOADING = `<div style='padding: 16px'><img style="margin-top: 10px;" src="data:image/svg+xml,%3csvg%20width='191'%20height='48'%20viewBox='0%200%20191%2048'%20fill='none'%20xmlns='http://www.w3.org/2000/svg'%3e%3cpath%20d='M72.7485%208H67.5859V40.2689H75.0474V27.321H86.7035V21.0689H75.0474V14.6958H87.1875V8H75.0474H72.7485Z'%20fill='%23001F3F'/%3e%3cpath%20d='M98.6045%208H90.8203V40.2689H111.551V33.5731H98.6045V8Z'%20fill='%23001F3F'/%3e%3cpath%20d='M123.086%208H115.141V40.2689H123.086V8Z'%20fill='%23001F3F'/%3e%3cpath%20d='M129.132%208V14.9781H141.958L126.914%2040.2689H152.767V33.5731H139.175L154.421%208H129.132Z'%20fill='%23001F3F'/%3e%3cpath%20d='M167.534%2022.1143C167.534%2021.2672%20167.333%2020.5008%20166.97%2019.8958C166.607%2019.2907%20166.042%2018.8067%20165.316%2018.484C164.59%2018.1613%20163.703%2018%20162.614%2018H158.258V30.5849H161.283V26.2286H162.614C163.703%2026.2286%20164.59%2026.0672%20165.316%2025.7445C166.042%2025.4218%20166.607%2024.9378%20166.97%2024.3328C167.373%2023.6874%20167.534%2022.9613%20167.534%2022.1143ZM164.106%2023.3244C163.743%2023.6067%20163.299%2023.7277%20162.654%2023.7277H161.323V20.5008H162.654C163.259%2020.5008%20163.743%2020.6218%20164.106%2020.9042C164.469%2021.1865%20164.63%2021.5899%20164.63%2022.1143C164.63%2022.6386%20164.429%2023.042%20164.106%2023.3244Z'%20fill='%23001F3F'/%3e%3cpath%20d='M166.891%2030.3916H170.319L171.489%2027.9714H175.522L176.691%2030.3916H180.16L173.505%2017L166.891%2030.3916ZM174.917%2025.6723H172.134L173.545%2022.4454L174.917%2025.6723Z'%20fill='%23001F3F'/%3e%3cpath%20d='M187.258%2018L184.797%2022.7193L182.337%2018H178.828L183.224%2025.5025V30.6252H186.33V25.5025L190.726%2018.0403H187.258V18Z'%20fill='%23001F3F'/%3e%3cpath%20d='M39.5304%2031.0187H29.4995L40.7842%2012.1011H7.8125V35.9884H13.3339V26.3801H21.9586V21.7414H13.3852V17.0221H22.0392H31.3087L20.4271%2035.9397H39.5304V31.0187Z'%20fill='%23001F3F'/%3e%3cpath%20d='M24.2533%200C10.9938%200%200.273438%2010.7697%200.273438%2024C0.273438%2037.2706%2011.0342%2048%2024.2533%2048C37.5127%2048%2048.2734%2037.2303%2048.2734%2024C48.2734%2010.7294%2037.5127%200%2024.2533%200ZM20.4246%2035.9395L31.3465%2017.0219H22.077H13.3717V21.7412H21.9561V26.3798H13.3314V35.9798H7.80996V12.1008H40.7772L29.4926%2031.0185H39.5279V35.9395H20.4246Z'%20fill='%2380ED99'/%3e%3c/svg%3e"/><br /><img src="data:image/svg+xml,%3csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%20100%20100'%20preserveAspectRatio='xMidYMid'%20width='200'%20height='200'%20style='shape-rendering:%20auto;%20display:%20block;%20background:%20rgb(255,%20255,%20255);'%20xmlns:xlink='http://www.w3.org/1999/xlink'%3e%3cg%3e%3cg%20transform='rotate(0%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.9166666666666666s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(30%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.8333333333333334s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(60%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.75s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(90%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.6666666666666666s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(120%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.5833333333333334s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(150%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.5s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(180%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.4166666666666667s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(210%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.3333333333333333s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(240%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.25s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(270%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.16666666666666666s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(300%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='-0.08333333333333333s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%20transform='rotate(330%2050%2050)'%3e%3crect%20fill='%2387fe71'%20height='12'%20width='6'%20ry='6'%20rx='3'%20y='24'%20x='47'%3e%3canimate%20repeatCount='indefinite'%20begin='0s'%20dur='1s'%20keyTimes='0;1'%20values='1;0'%20attributeName='opacity'%3e%3c/animate%3e%3c/rect%3e%3c/g%3e%3cg%3e%3c/g%3e%3c/g%3e%3c!----%3e%3c/svg%3e"/></div>`;
      setTimeout(() => {
        const isBLocks = document.querySelector("form.wc-block-checkout__form");
        if (isBLocks) {
          const unsubscribe = wp.data.subscribe(() => {
            const checkoutStatus = wp.data
              .select("wc/store/checkout")
              .getCheckoutStatus();
            if (
              checkoutStatus === "complete" &&
              document.querySelector(
                "#radio-control-wc-payment-method-options-flizpay"
              ).checked === true
            ) {
              jQuery.blockUI({
                css: {
                  border: "none",
                  borderRadius: "10%",
                  padding: "10px",
                },
                message: FLIZ_LOADING,
                overlayCSS: {
                  background: "#000",
                  opacity: 0.6,
                  cursor: "wait",
                },
              });
              mobile_redirect_when_order_finished(
                wp.data.select("wc/store/checkout").getOrderId()
              );
              unsubscribe();
            }
          });
        }

        const isClassic = document.querySelector("form.woocommerce-checkout");
        if (isClassic) {
          jQuery(document.body).on("checkout_error", () => {
            jQuery.unblockUI();
            stopPolling = true;
          });

          jQuery(isClassic).on("checkout_place_order_flizpay", () => {
            jQuery(document).ajaxComplete(function (_, xhr, settings) {
              if (settings.url.indexOf("wc-ajax=checkout") !== -1) {
                const response = xhr.responseJSON;

                if (response && response.result === "success") {
                  if (response.order_id) {
                    stopPolling = false;
                    jQuery.blockUI({
                      css: {
                        border: "none",
                        borderRadius: "10%",
                        padding: "10px",
                      },
                      message: FLIZ_LOADING,
                      overlayCSS: {
                        background: "#000",
                        opacity: 0.6,
                        cursor: "wait",
                      },
                    });
                    mobile_redirect_when_order_finished(response.order_id);
                  }
                }
              }
            });
          });
        }
      }, 1700);

      const mobile_redirect_when_order_finished = (order_id) => {
        if (stopPolling) {
          jQuery.unblockUI();
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
                mobile_redirect_when_order_finished(order_id);
              } else {
                window.location.href = order.url;
              }
            }, 2000);
          },
        });
      };
    } catch (err) {
      console.log(err);
    }
  });
});
