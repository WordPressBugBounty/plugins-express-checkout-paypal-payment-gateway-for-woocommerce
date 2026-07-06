;( function ( $, window, document ) {
	'use strict';

	var post_data = {type:'ajax'};
	var tagline   = true;
	if (eh_button_params['tagline'] == 'show') {
		tagline = true;
	} else {
		tagline = false;
	}
	$( '.eh_spinner' ).hide();
	if (document.getElementById( 'paypal-checkout-button-render' ) && eh_button_params['express_button']) {
		
		var render = function () {
			paypal.Buttons(
				{

					env: eh_button_params['environment'],  // sandbox | production
					locale: eh_button_params['locale'],
					style: {
						size: eh_button_params['size'],   // tiny | small | medium
						color: eh_button_params['color'],	// gold | blue | silver
						shape: eh_button_params['shape'],	// pill | rect
						label: eh_button_params['label'],// checkout | credit
						tagline: tagline, // checkout | credit
						layout: eh_button_params['layout'] // checkout | credit
					},
					createOrder: function() {
						var qty = $( '.qty' ).val();
						if ( ! qty) {
							qty = $( "input[name=quantity]" ).val();
						}

						return fetch(
							eh_button_params['express_url'],
							{
								method: 'POST',
								headers: {
									'Accept': 'application/json',
									'Content-Type': 'application/json'
								},
								body: JSON.stringify( post_data )
							}
						).then(
							function(res) {
								return res.json();
							}
						).then(
							function(data) {
								return data.id;
							}
						);

					},

					onApprove: function(data) {
						$( '.eh_spinner' ).show();
						window.location.href = eh_button_params['return_url'] + '&token=' + data.orderID;

					},

					onShippingChange: function(data, actions) {
						var country  = ( data.shipping_address && data.shipping_address.country_code )
							? data.shipping_address.country_code : '';
						var state    = ( data.shipping_address && data.shipping_address.state )
							? data.shipping_address.state : '';
						var postcode = ( data.shipping_address && data.shipping_address.postal_code )
							? data.shipping_address.postal_code : '';
						var city     = ( data.shipping_address && data.shipping_address.city )
							? data.shipping_address.city : '';

						if ( ! country ) {
							return actions.resolve();
						}

						// Validate country restriction
						return fetch( eh_button_params['ajax_url'], {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: new URLSearchParams({
								action          : 'eh_smart_button_recalculate_totals',
								nonce           : eh_button_params['nonce'],
								country         : country,
								state           : state,
								postcode        : postcode,
								city            : city,
								paypal_order_id : data.orderID
							})
						} )
						.then( function( res ) { return res.json(); } )
						.then( function( resp ) {
							// If country is restricted, reject so PayPal shows its native error.
							if ( resp && resp.allowed === false ) {
								return actions.reject();
							}
							return actions.resolve();
						} )
						.catch( function() {
							return actions.resolve();
						} );
					},

					onError: function(err){
						console.log( err );
						if (err == 'Error: Unexpected end of JSON input') {
							$( '.eh_spinner' ).show();
							window.location.href = eh_button_params['p'];
						} else {
							alert( 'Unable to proceed payment.' );

						}

					},

					onCancel: function(data, actions) {
						$( '.eh_spinner' ).show();
						window.location.href = eh_button_params['cancel_url']
					}

				}
			).render( '#paypal-checkout-button-render' );

			if ($('#paypal-checkout-button-render').length == 0) {
				$('.eh_paypal_express_description').hide();
			}
		};
		render();
		$( document.body ).on( 'updated_cart_totals', render.bind( this, false ) );

	}

} )( jQuery, window, document );
