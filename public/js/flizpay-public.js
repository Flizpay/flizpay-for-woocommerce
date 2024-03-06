(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 */

	$(function() {
		$(document).ready(function (){
			if( document.querySelector( '.wc-block-checkout__form' ) ) {
				var fc = "form.wc-block-checkout__form",
					pl = '.wc-block-components-checkout-place-order-button',
					payment_sel = 'input[name="radio-control-wc-payment-method-options"]',
					is_block = 'yes';
			} else {
				var fc = "form.woocommerce-checkout",
					pl = 'button[type="submit"][name="woocommerce_checkout_place_order"]',
					payment_sel = 'input[name="payment_method"]',
					is_block = 'no';
			}

			initilize_flizpay_payment_process(fc, pl, payment_sel, is_block)
		})

		// Hide payment fail menu item
		$('ul li a').each(
			function(key, item){
				if( item.text === 'Flizpay Payment Fail' ) {
					$( this ).closest( 'li' ).remove()
				}
			}
		)
	});
	/**
	 * When the window is loaded:
	 *
	  $( window ).load(function() {
		  // initilize_flizpay_payment_process()
	  });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

	function initilize_flizpay_payment_process(fc, pl, payment_sel, is_block) {
		$(fc).on("click", pl, function (e) {
			var chosen_payment = $(payment_sel+':checked').val();
			if( chosen_payment === 'flizpay' ) {
				e.preventDefault();
				e.stopImmediatePropagation()

				if( is_block === 'yes') {
					$(this).addClass('wc-block-components-button--loading')
					$('.wc-block-components-button__text').before(
						'<span class="wc-block-components-spinner" aria-hidden="true"></span>'
					)
				} else {
					$(this).text('Please Wait...')
				}

				payment_gateway_function(fc, pl, chosen_payment )
			} else {
				jQuery(fc).off();
				jQuery(pl).trigger("click");
			}

		});
	}

	/**
	 *
	 * @param fc
	 * @param pl
	 * @param is_block
	 */
	function payment_gateway_function( fc, pl, chosen_payment ) {
		let validation = 'pass'
		$( fc+' input' ).not(':hidden').each( function() {
			if( ( $(this).is('[required]') || $(this).parent().closest('#'+$(this).attr('id')+'_field').hasClass('validate-required') )
				&& $(this).val().length === 0 ) {
				validation = 'fail'
			}
		} )

		if (validation === 'pass') {
			get_order_data(chosen_payment)
		} else {
			jQuery(fc).off();
			jQuery(pl).trigger("click");
			initilize_flizpay_payment_process()
		}
	}

	/**
	 *
	 */
	function get_order_data(chosen_payment) {
		$(document.body).trigger('update_checkout');

		var data = {
			'action': 'flizpay_get_payment_data'
		};

		jQuery.ajax({
			url: flizpay_frontend.ajaxurl,
			type: 'POST',
			data: data,
			success: function (response) {
				load_flizpay_modal(chosen_payment, response)
				flizpay_load_order_finish_page(response)
			}
		});
	}

	/**
	 *
	 * @param chosen_payment
	 */
	function load_flizpay_modal(chosen_payment, json) {
		var returned_data = JSON.parse(json)
		// var url = 'https://chart.googleapis.com/chart?cht=qr&chl='+json+'&chs=160x160&chld=L|0'
		if ($('.confirmation-modal').length === 0 ) {
			var confirmation_modal   = '<div class="confirmation-modal">' +
				'<div class="modal-content">' +
				'<div class="flizpay-container">' +
				'<div class="header">' +
				'<div class="logo">' +
				'<img src="'+flizpay_frontend.public_dir_path+'/image/logo.png">' +
				'</div>' +
				'<div class="cart-info">' +
				'<img src="'+flizpay_frontend.public_dir_path+'/image/trolley.png" width="20"><span>'+returned_data.total+returned_data.currency+'</span>' +
				'</div>' +
				'</div>' +
				'<p><i>ⓘ Please scan the QR-Code with your Fliz app in order to pay.</i></p>' +
				// '<img src='+url+' width="300" alt="QR Code" class="flizpay-qr-code">' +
				'<div id="flizpay_payment_qrcode"></div>' +
				'</div>' +
				'</div>' +
				'</div>';

			if ( chosen_payment === 'flizpay' ) {

				$('body').append(confirmation_modal);
				generate_qrcode(200, 200, json );
			}
		}
	}

	/**
	 *
	 * @param width
	 * @param height
	 * @param text
	 */
	function generate_qrcode(width, height, text) {
		jQuery('#flizpay_payment_qrcode').qrcode({width: width,height: height,text: text});
	}

	/**
	 *
	 * @param json
	 */
	function flizpay_load_order_finish_page(json) {

		var returned_data = JSON.parse(json)
		var data = {
			'action': 'flizpay_order_finish',
			'order_id': returned_data.order_id
		};

		jQuery.ajax({
			url: flizpay_frontend.ajaxurl,
			type: 'POST',
			data: data,
			success: function (response) {
				window.setTimeout(function() {
					var load = JSON.parse(response)
					if( load.status == 'pending' ) {
						flizpay_load_order_finish_page(json)
					} else {
						window.location.href = load.url
					}
				}, 5000);
			}
		});
	}
})( jQuery );

