(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	jQuery(document).ready(function($) {
    $('#woocommerce_flizpay_test_connection').after('<button id="test_connection_button" type="button">Test Connection</button>');

    $('#test_connection_button').on('click', function() {
        var apiKey = $('#woocommerce_flizpay_api_key').val();

        $.ajax({
            url: 'api.flizpay.de/test-connection',
            method: 'POST',
            data: {
                action: 'test_gateway_connection',
                api_key: apiKey,
            },
            success: function(response) {
                if (response.success) {
                    alert('Connection successful: ' + response.data);
                } else {
                    alert('Connection failed: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while testing the connection.');
            }
        });
    });
});
})(jQuery);
