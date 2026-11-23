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
		var renderTimeout = null;
		var renderGeneration = 0;
		var lastBlikRenderKey = null;
		var checkoutValidationFailed = false;
		var createOrderError = '';
		var showCheckoutError = function ( message, messages ) {
			var checkoutForm = $( 'form.checkout' );
			if ( eh_button_params.page_name !== 'checkout' || ! checkoutForm.length ) {
				return false;
			}
			checkoutValidationFailed = true;
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			var noticeGroup = $( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"></div>' );
			if ( messages ) {
				noticeGroup.html( messages );
			} else {
				noticeGroup.append( $( '<ul class="woocommerce-error" role="alert"></ul>' ).append( $( '<li></li>' ).text( message ) ) );
			}
			checkoutForm.prepend( noticeGroup );
			checkoutForm.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).trigger( 'blur' );
			if ( $.scroll_to_notices ) {
				$.scroll_to_notices( noticeGroup );
			}
			$( document.body ).trigger( 'checkout_error', [ noticeGroup.html() ] );
			return true;
		};
		var scheduleRender = function () {
			clearTimeout( renderTimeout );
			renderTimeout = setTimeout( render, 100 );
		};
		var cartCountry = function () {
			return eh_button_params.cart_billing_country || $( '#calc_shipping_country' ).val() || eh_button_params.cart_shipping_country || '';
		};
		var isLocalPaymentCountryEligible = function ( country ) {
			if ( eh_button_params.page_name === 'checkout' ) {
				return $( 'form.checkout #billing_country' ).val() === country;
			}
			if ( eh_button_params.page_name === 'cart' && ( country === 'BE' || country === 'NL' || country === 'PL' ) ) {
				return cartCountry() === country;
			}
			return false;
		};
		var createOrderFor = function ( fundingSource ) {
			return function ( data ) {
				checkoutValidationFailed = false;
				createOrderError = '';
				var selectedFundingSource = fundingSource || ( data && data.paymentSource );
				var requestData = $.extend( {}, post_data );
				if ( eh_button_params.page_name === 'checkout' ) {
					requestData.eh_paypal_checkout_form_data = $( 'form.checkout' ).find( ':input[name]' ).filter( function () {
						return this.name !== 'payment_method' && this.name !== 'createaccount' &&
							this.name !== 'account_username' && this.name !== 'account_password' &&
							! $( this ).closest( '.payment_box' ).length;
					} ).serialize();
				}
				if ( selectedFundingSource ) {
					requestData.funding_source = selectedFundingSource;
					if ( ( selectedFundingSource === 'bancontact' || selectedFundingSource === 'ideal' || selectedFundingSource === 'blik' ) && eh_button_params.page_name === 'cart' ) {
						requestData.billing_first_name = eh_button_params.cart_billing_first_name || '';
						requestData.billing_last_name = eh_button_params.cart_billing_last_name || '';
						requestData.billing_country = cartCountry();
						requestData.billing_email = eh_button_params.cart_billing_email || '';
						requestData.billing_phone = eh_button_params.cart_billing_phone || '';
					}
				}
				return fetch( eh_button_params.express_url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
					body: JSON.stringify( requestData )
				} ).then( function ( res ) { return res.json(); } )
				  .then( function ( data ) {
					if ( ! data || ! data.id ) {
						createOrderError = data && typeof data.error === 'string' ? data.error : 'PayPal order could not be created.';
						showCheckoutError( createOrderError, data && data.messages );
						throw new Error( createOrderError );
					}
					return data.id;
				  } );
			};
		};

		var render = function () {
			var buttonContainer = $( '#paypal-checkout-button-render' );
			if ( eh_button_params.blik_enabled === 'yes' ) {
				// Cart and checkout can refresh; keep hosted fields mounted when eligibility is unchanged.
				var renderCountry = eh_button_params.page_name === 'cart' ? cartCountry() : ( $( 'form.checkout #billing_country' ).val() || '' );
				var renderKey = renderCountry + '|' +
					( Number( $( '#eh-paypal-cart-total' ).attr( 'data-total' ) ) >= 1 );
				if ( renderKey === lastBlikRenderKey && buttonContainer.children().length ) {
					return;
				}
				lastBlikRenderKey = renderKey;
			}
			var generation = ++renderGeneration;
			buttonContainer.empty().append( '<div class="eh-paypal-button-container"></div>' );
			var buttonOptions = {

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
					createOrder: createOrderFor(),

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
						if ( checkoutValidationFailed ) {
							$( '.eh_spinner' ).hide();
							if ( $.scroll_to_notices ) {
								$.scroll_to_notices( $( '.woocommerce-NoticeGroup-checkout' ) );
							}
							return;
						}
						if ( showCheckoutError( createOrderError || 'Unable to proceed payment. Please try again.' ) ) {
							$( '.eh_spinner' ).hide();
							return;
						}
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

				};
			// A dedicated BLIK button needs PaymentFields and cannot have shipping callbacks.
			// Render the other eligible sources separately so the automatic group cannot duplicate BLIK.
			if ( eh_button_params.blik_enabled === 'yes' ) {
				paypal.getFundingSources().forEach( function ( source ) {
					if ( source === paypal.FUNDING.BLIK ) {
						return;
					}
					var options = $.extend( {}, buttonOptions, { fundingSource: source } );
					if ( source !== paypal.FUNDING.PAYPAL ) {
						options.style = { shape: eh_button_params.shape, layout: 'vertical' };
					}
					var buttons = paypal.Buttons( options );
					if ( buttons.isEligible() ) {
						var container = document.createElement( 'div' );
						buttonContainer.find( '.eh-paypal-button-container' ).append( container );
						buttons.render( container ).catch( buttonOptions.onError );
					}
				} );
			} else {
				paypal.Buttons( buttonOptions ).render( '#paypal-checkout-button-render .eh-paypal-button-container' );
			}

			var renderLocalPaymentMethod = function ( source, country ) {
				var funding = paypal.FUNDING && paypal.FUNDING[ source.toUpperCase() ];
				if ( ! isLocalPaymentCountryEligible( country ) || eh_button_params[ source + '_enabled' ] !== 'yes' || ! funding || ( ( source === 'ideal' || source === 'blik' ) && typeof paypal.PaymentFields !== 'function' ) ) {
					return;
				}
				if ( source === 'blik' && ! ( Number( $( '#eh-paypal-cart-total' ).attr( 'data-total' ) ) >= 1 ) ) {
					return;
				}
				var localButton = paypal.Buttons( {
					fundingSource: funding,
					style: { label: 'pay' },
					createOrder: createOrderFor( source ),
					onApprove: buttonOptions.onApprove,
					onCancel: buttonOptions.onCancel,
					onError: buttonOptions.onError
				} );
				if ( typeof localButton.isEligible !== 'function' || localButton.isEligible() ) {
					var fieldsSelector = '#paypal-checkout-button-render .eh-' + source + '-fields-container';
					var buttonSelector = '#paypal-checkout-button-render .eh-' + source + '-button-container';
					buttonContainer.append( '<div class="eh-' + source + '-fields-container"></div><div class="eh-' + source + '-button-container"></div>' );
					if ( source === 'blik' ) {
						try {
							var blikName = eh_button_params.page_name === 'cart'
								? $.trim( ( eh_button_params.cart_billing_first_name || '' ) + ' ' + ( eh_button_params.cart_billing_last_name || '' ) )
								: $.trim( ( $( '#billing_first_name' ).val() || '' ) + ' ' + ( $( '#billing_last_name' ).val() || '' ) );
							var blikEmail = eh_button_params.page_name === 'cart' ? ( eh_button_params.cart_billing_email || '' ) : ( $( '#billing_email' ).val() || '' );
							Promise.resolve( paypal.PaymentFields( {
								fundingSource: funding,
								fields: {
									name: { value: blikName },
									email: { value: blikEmail }
								}
							} ).render( fieldsSelector ) ).then( function () {
								if ( generation === renderGeneration ) {
									return localButton.render( buttonSelector );
								}
							} ).catch( function ( err ) {
								if ( generation === renderGeneration ) {
									$( fieldsSelector + ', ' + buttonSelector ).remove();
								}
								console.warn( 'Unable to render BLIK.', err );
							} );
						} catch ( err ) {
							$( fieldsSelector + ', ' + buttonSelector ).remove();
							console.warn( 'Unable to render BLIK.', err );
						}
						return;
					}
					if ( typeof paypal.PaymentFields === 'function' ) {
						try {
							var fieldsRender = paypal.PaymentFields( { fundingSource: funding } ).render( fieldsSelector );
							if ( fieldsRender && typeof fieldsRender.catch === 'function' ) {
								fieldsRender.catch( function ( err ) {
									if ( source === 'ideal' ) {
										$( buttonSelector ).remove();
									}
									if ( window.console && console.warn ) {
										console.warn( 'Unable to render ' + source + ' payment fields.', err );
									}
								} );
							}
						} catch ( err ) {
							if ( source === 'ideal' ) {
								$( buttonSelector ).remove();
							}
							if ( window.console && console.warn ) {
								console.warn( 'Unable to render ' + source + ' payment fields.', err );
							}
						}
					}
					localButton.render( buttonSelector ).catch( function ( err ) {
						if ( window.console && console.warn ) {
							console.warn( 'Unable to render ' + source + ' button.', err );
						}
					} );
				}
			};
			renderLocalPaymentMethod( 'bancontact', 'BE' );
			renderLocalPaymentMethod( 'ideal', 'NL' );
			renderLocalPaymentMethod( 'blik', 'PL' );

			if ($('#paypal-checkout-button-render').length == 0) {
				$('.eh_paypal_express_description').hide();
			}
		};
		render();
		$( document.body ).on( 'updated_cart_totals', render.bind( this, false ) );
		$( document.body ).on( 'change', '#calc_shipping_country', scheduleRender );
		$( document.body ).on( 'updated_checkout', scheduleRender );
		$( document.body ).on( 'change', 'form.checkout #billing_country', scheduleRender );

	}

} )( jQuery, window, document );
