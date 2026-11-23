( function () {
	'use strict';

	if ( ! window.wc || ! window.wc.wcBlocksRegistry || ! window.wc.wcSettings || ! window.wp || ! window.wp.element ) {
		return;
	}

	var registerExpressPaymentMethod = window.wc.wcBlocksRegistry.registerExpressPaymentMethod;
	var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting = window.wc.wcSettings.getSetting;
	var decodeEntities = ( window.wp.htmlEntities && window.wp.htmlEntities.decodeEntities )
		? window.wp.htmlEntities.decodeEntities
		: function ( text ) { return text; };

	var el = window.wp.element.createElement;
	var useEffect = window.wp.element.useEffect;
	var useRef = window.wp.element.useRef;

	var settings = getSetting( 'eh_paypal_express_data', {} );
	var redirecting = false;
	var createOrderError = '';
	var checkoutErrorDisplayed = false;

	/* ---------------------------------------------------------------------
	 * PayPal JS SDK loader — the SDK URL is built server-side (client ID,
	 * currency, locale, funding sources) like the
	 * classic flow, so it must be injected dynamically rather than declared
	 * as a static script dependency.
	 * ------------------------------------------------------------------- */
	var sdkPromise = null;
	var sdkNamespace = 'ehPayPalExpress';
	function loadPayPalSdk( url ) {
		if ( window[ sdkNamespace ] && typeof window[ sdkNamespace ].Buttons === 'function' ) {
			return Promise.resolve( window[ sdkNamespace ] );
		}
		if ( sdkPromise ) {
			return sdkPromise;
		}
		sdkPromise = new Promise( function ( resolve, reject ) {
			var onLoad = function () {
				var sdk = window[ sdkNamespace ];
				if ( sdk && typeof sdk.Buttons === 'function' ) {
					resolve( sdk );
				} else {
					reject( new Error( 'PayPal Smart Buttons SDK did not initialize.' ) );
				}
			};
			var existing = document.querySelector( 'script[data-eh-paypal-express-sdk="1"]' );
			if ( existing ) {
				existing.addEventListener( 'load', onLoad );
				existing.addEventListener( 'error', reject );
				return;
			}
			var script = document.createElement( 'script' );
			script.src = url;
			script.async = true;
			script.setAttribute( 'data-eh-paypal-express-sdk', '1' );
			// Other gateways may expose a different SDK version at window.paypal.
			script.setAttribute( 'data-namespace', sdkNamespace );
			script.onload = onLoad;
			script.onerror = reject;
			document.head.appendChild( script );
		} );
		return sdkPromise;
	}

	function showProcessingOverlay() {
		if ( document.getElementById( 'eh-paypal-express-processing' ) ) {
			return;
		}
		var overlay = document.createElement( 'div' );
		overlay.id = 'eh-paypal-express-processing';
		overlay.setAttribute(
			'style',
			'position:fixed;top:0;left:0;width:100%;height:100%;background:#fff;' +
			'z-index:999999;display:flex;align-items:center;justify-content:center;flex-direction:column;'
		);
		overlay.innerHTML =
			'<div style="width:40px;height:40px;border:4px solid #ccc;border-top:4px solid #003087;' +
			'border-radius:50%;animation:eh-pp-express-spin 1s linear infinite;"></div>' +
			'<style>@keyframes eh-pp-express-spin{100%{transform:rotate(360deg)}}</style>';
		document.body.appendChild( overlay );
		document.body.style.overflow = 'hidden';
	}

	function hideProcessingOverlay() {
		var overlay = document.getElementById( 'eh-paypal-express-processing' );
		if ( overlay ) {
			overlay.parentNode.removeChild( overlay );
		}
		document.body.style.overflow = '';
	}

	function buttonStyle() {
		return {
			color: settings.color || 'gold',
			shape: settings.shape || 'rect',
			label: settings.label || 'checkout',
			tagline: settings.tagline === 'show',
			layout: settings.layout || 'vertical',
		};
	}

	function applyContainerStyle( containerEl ) {
		var attr = settings.container_style_attr || '';
		if ( ! attr || ! containerEl ) {
			return;
		}
		var match = attr.match( /style\s*=\s*"([^"]*)"/i );
		var css = match ? match[ 1 ] : attr;
		if ( css ) {
			var existing = containerEl.getAttribute( 'style' ) || '';
			containerEl.setAttribute( 'style', existing ? existing + ';' + css : css );
		}
	}

	function isLocalPayment( source ) {
		return source === 'bancontact' || source === 'ideal' || source === 'blik';
	}

	function showCheckoutError( message, checkoutRef ) {
		var props = checkoutRef.current || {};
		if ( typeof props.onError === 'function' ) {
			checkoutErrorDisplayed = true;
			props.onError( message );
		}
	}

	function validateCheckout( source, checkoutRef ) {
		if ( ! settings.is_checkout || ! isLocalPayment( source ) ) {
			return true;
		}
		var validation = window.wp.data.select( 'wc/store/validation' );
		if ( validation.hasValidationErrors() ) {
			window.wp.data.dispatch( 'wc/store/validation' ).showAllValidationErrors();
			createOrderError = settings.checkout_validation_error || 'Please check the highlighted checkout fields.';
			showCheckoutError( createOrderError, checkoutRef );
			return false;
		}
		return true;
	}

	function onClickFor( fundingSource, checkoutRef ) {
		return function ( data, actions ) {
			checkoutErrorDisplayed = false;
			var source = fundingSource || ( data && ( data.fundingSource || data.paymentSource ) );
			return validateCheckout( source, checkoutRef ) ? actions.resolve() : actions.reject();
		};
	}

	function createOrderFor( fundingSource, checkoutRef ) {
		return function ( data ) {
		redirecting = false;
		createOrderError = '';
		checkoutErrorDisplayed = false;
		var source = fundingSource || ( data && data.paymentSource );
		if ( ! validateCheckout( source, checkoutRef ) ) {
			return Promise.reject( new Error( createOrderError ) );
		}
		// Read the current props at click time; editing an address must not leave a stale SDK callback.
		var props = checkoutRef.current || {};
		var billingAddress = ( props.billing && props.billing.billingAddress ) || {};
		var requestData = { type: 'ajax' };
		if ( source ) {
			requestData.funding_source = source;
			requestData.billing_first_name = billingAddress.first_name || '';
			requestData.billing_last_name = billingAddress.last_name || '';
			requestData.billing_country = billingAddress.country || '';
			requestData.billing_email = billingAddress.email || '';
			requestData.billing_phone = billingAddress.phone || '';
		}
		if ( settings.is_checkout && isLocalPayment( source ) ) {
			var formData = new URLSearchParams();
			var shippingData = props.shippingData || {};
			var addresses = { billing: billingAddress, shipping: shippingData.shippingAddress || {} };
			Object.keys( addresses ).forEach( function ( group ) {
				Object.keys( addresses[ group ] ).forEach( function ( key ) {
					var value = addresses[ group ][ key ];
					formData.set( group + '_' + key, typeof value === 'boolean' ? ( value ? '1' : '' ) : value );
				} );
			} );
			formData.set( 'ship_to_different_address', shippingData.needsShipping ? '1' : '0' );
			var checkout = window.wp.data.select( 'wc/store/checkout' );
			var additionalFields = typeof checkout.getAdditionalFields === 'function' ? ( checkout.getAdditionalFields() || {} ) : {};
			Object.keys( additionalFields ).forEach( function ( key ) {
				var value = additionalFields[ key ];
				formData.set( key, typeof value === 'boolean' ? ( value ? '1' : '' ) : value );
			} );
			formData.set( 'order_comments', checkout.getOrderNotes() || '' );
			requestData.eh_paypal_checkout_form_data = formData.toString();
			requestData.eh_paypal_block_checkout = true;
		}
		return fetch( settings.express_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( requestData ),
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( data ) {
				if ( ! data || ! data.id ) {
					createOrderError = data && typeof data.error === 'string' ? data.error : 'PayPal order could not be created.';
					if ( settings.is_checkout && isLocalPayment( source ) ) {
						showCheckoutError( createOrderError, checkoutRef );
					}
					throw new Error( createOrderError );
				}
				return data.id;
			} );
		};
	}

	function onApprove( data ) {
		redirecting = true;
		showProcessingOverlay();
		window.location.href = settings.return_url + '&token=' + data.orderID;
	}

	function onCancel() {
		redirecting = true;
		showProcessingOverlay();
		window.location.href = settings.cancel_url;
	}

	function onError() {
		if ( redirecting || checkoutErrorDisplayed ) {
			return;
		}
		hideProcessingOverlay();
		window.alert( createOrderError || 'Unable to proceed with PayPal payment. Please try again.' );
	}

	function onShippingChange( data, actions ) {
		var addr = data.shipping_address || {};
		var selectedOption = data.selected_shipping_option && data.selected_shipping_option.id;
		if ( ! addr.country_code ) {
			return actions.reject();
		}
		var requestData = {
			action: 'eh_smart_button_recalculate_totals',
			nonce: settings.nonce || '',
			country: addr.country_code,
			state: addr.state || '',
			postcode: addr.postal_code || '',
			city: addr.city || '',
			paypal_order_id: data.orderID,
		};
		if ( selectedOption ) {
			requestData.selected_shipping_option = selectedOption;
		}
		return fetch( settings.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams( requestData ),
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( resp ) {
				if ( ! resp || resp.success !== true || resp.allowed === false ) {
					return actions.reject();
				}
				return actions.resolve();
			} )
			.catch( function () {
				return actions.reject();
			} );
	}

	/**
	 * Content — mounts the real paypal.Buttons() widget into a container div.
	 * WooCommerce Blocks clones this element and injects billing/shippingData
	 * props at render time (same pattern used for other express methods).
	 */
	var Content = function ( props ) {
		var containerRef = useRef( null );
		var checkoutRef = useRef( props );
		checkoutRef.current = props;
		var needsShipping = !! ( props && props.shippingData && props.shippingData.needsShipping );
		var billingAddress = ( props && props.billing && props.billing.billingAddress ) || {};
		var billingCountry = billingAddress.country || '';
		// Blocks exposes cart totals in minor units (PLN has two decimal places).
		var cartTotal = props && props.billing && props.billing.cartTotal ? Number( props.billing.cartTotal.value ) : 0;

		useEffect(
			function () {
				var cancelled = false;

				if ( ! settings.sdk_url || ! containerRef.current ) {
					return undefined;
				}

				loadPayPalSdk( settings.sdk_url )
					.then( function ( paypal ) {
						if ( cancelled || ! paypal || ! containerRef.current ) {
							return;
						}
						containerRef.current.innerHTML = '';
						applyContainerStyle( containerRef.current );
						var mainContainer = document.createElement( 'div' );
						mainContainer.className = 'eh-paypal-button-container';
						containerRef.current.appendChild( mainContainer );

						var buttonOptions = {
							style: buttonStyle(),
							createOrder: createOrderFor( undefined, checkoutRef ),
							onClick: onClickFor( undefined, checkoutRef ),
							onApprove: onApprove,
							onCancel: onCancel,
							onError: onError,
							onShippingChange: onShippingChange,
						};

						if ( settings.blik_enabled === 'yes' ) {
							// Keep BLIK out of the main group; it needs fields and no shipping callbacks.
							paypal.getFundingSources().forEach( function ( source ) {
								if ( source === paypal.FUNDING.BLIK ) {
									return;
								}
								var options = Object.assign( {}, buttonOptions, { fundingSource: source } );
								options.createOrder = createOrderFor( source, checkoutRef );
								options.onClick = onClickFor( source, checkoutRef );
								if ( source !== paypal.FUNDING.PAYPAL ) {
									options.style = { shape: settings.shape || 'rect', layout: 'vertical' };
								}
								var buttons = paypal.Buttons( options );
								if ( buttons.isEligible() ) {
									var container = document.createElement( 'div' );
									mainContainer.appendChild( container );
									buttons.render( container ).catch( onError );
								}
							} );
						} else {
							var buttons = paypal.Buttons( buttonOptions );
							if ( typeof buttons.isEligible !== 'function' || buttons.isEligible() ) {
								buttons.render( mainContainer );
							}
						}

						var renderLocalPaymentMethod = function ( source, country ) {
							var funding = paypal.FUNDING && paypal.FUNDING[ source.toUpperCase() ];
							if ( billingCountry !== country || settings[ source + '_enabled' ] !== 'yes' || ! funding || ( ( source === 'ideal' || source === 'blik' ) && typeof paypal.PaymentFields !== 'function' ) ) {
								return;
							}
							if ( source === 'blik' && ! ( cartTotal >= 100 ) ) {
								return;
							}
							var localButton = paypal.Buttons( {
								fundingSource: funding,
								style: { label: 'pay' },
								createOrder: createOrderFor( source, checkoutRef ),
								onClick: onClickFor( source, checkoutRef ),
								onApprove: onApprove,
								onCancel: onCancel,
								onError: onError,
							} );
							if ( typeof localButton.isEligible !== 'function' || localButton.isEligible() ) {
								var fieldsContainer = document.createElement( 'div' );
								fieldsContainer.className = 'eh-' + source + '-fields-container';
								var localContainer = document.createElement( 'div' );
								localContainer.className = 'eh-' + source + '-button-container';
								containerRef.current.appendChild( fieldsContainer );
								containerRef.current.appendChild( localContainer );
								if ( source === 'blik' ) {
									try {
										Promise.resolve( paypal.PaymentFields( {
											fundingSource: funding,
											fields: {
												name: { value: ( ( billingAddress.first_name || '' ) + ' ' + ( billingAddress.last_name || '' ) ).trim() },
												email: { value: billingAddress.email || '' }
											}
										} ).render( fieldsContainer ) ).then( function () {
											if ( ! cancelled ) {
												return localButton.render( localContainer );
											}
										} ).catch( function ( err ) {
											fieldsContainer.remove();
											localContainer.remove();
											console.warn( 'Unable to render BLIK.', err );
										} );
									} catch ( err ) {
										fieldsContainer.remove();
										localContainer.remove();
										console.warn( 'Unable to render BLIK.', err );
									}
									return;
								}
								if ( typeof paypal.PaymentFields === 'function' ) {
									try {
										var fieldsRender = paypal.PaymentFields( { fundingSource: funding } ).render( fieldsContainer );
										if ( fieldsRender && typeof fieldsRender.catch === 'function' ) {
											fieldsRender.catch( function ( err ) {
												if ( source === 'ideal' ) {
													localContainer.remove();
												}
												if ( window.console && console.warn ) {
													console.warn( 'Unable to render ' + source + ' payment fields.', err );
												}
											} );
										}
									} catch ( err ) {
										if ( source === 'ideal' ) {
											localContainer.remove();
										}
										if ( window.console && console.warn ) {
											console.warn( 'Unable to render ' + source + ' payment fields.', err );
										}
									}
								}
								localButton.render( localContainer ).catch( function ( err ) {
									if ( window.console && console.warn ) {
										console.warn( 'Unable to render ' + source + ' button.', err );
									}
								} );
							}
						};
						renderLocalPaymentMethod( 'bancontact', 'BE' );
						renderLocalPaymentMethod( 'ideal', 'NL' );
						renderLocalPaymentMethod( 'blik', 'PL' );

					} )
					.catch( function ( err ) {
						console.warn( 'Unable to initialize PayPal checkout.', err );
					} );

				return function () {
					cancelled = true;
				};
			},
			[ needsShipping, cartTotal, billingCountry, billingAddress.first_name, billingAddress.last_name, billingAddress.email, billingAddress.phone ]
		);

		return el(
			'div',
			{ className: 'eh-paypal-express-block-wrapper' },
			settings.description
				? el( 'p', { className: 'eh-paypal-express-block-description' }, decodeEntities( settings.description ) )
				: null,
			el( 'div', { ref: containerRef, className: 'eh-paypal-express-block-buttons' } )
		);
	};

	registerExpressPaymentMethod( {
		name: 'eh_paypal_smart_button',
		paymentMethodId: 'eh_paypal_express',
		title: decodeEntities( settings.title || 'PayPal' ),
		description: settings.description ? decodeEntities( settings.description ) : '',
		content: el( Content ),
		edit: el(
			'div',
			{ className: 'eh-paypal-express-block-edit' },
			decodeEntities( settings.title || 'PayPal Smart Button' )
		),
		canMakePayment: function () {
			return !! ( settings.sdk_url && settings.express_url && settings.hide_express_widget !== 'yes' );
		},
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );

	var RegularContent = function () {
		return settings.gateway_description ? decodeEntities( settings.gateway_description ) : '';
	};

	var RegularLabel = function ( props ) {
		var PaymentMethodLabel = props && props.components && props.components.PaymentMethodLabel;
		if ( ! PaymentMethodLabel ) {
			return decodeEntities( settings.title || 'PayPal' );
		}
		return el( PaymentMethodLabel, { text: decodeEntities( settings.title || 'PayPal' ) } );
	};

	registerPaymentMethod( {
		name: 'eh_paypal_express',
		gatewayId: 'eh_paypal_express',
		paymentMethodId: 'eh_paypal_express',
		label: el( RegularLabel ),
		content: el( RegularContent ),
		edit: el( RegularContent ),
		ariaLabel: decodeEntities( settings.title || 'PayPal' ),
		placeOrderButtonLabel: __eh_paypal_place_order_label(),
		canMakePayment: function () {
			return !! ( settings.sdk_url && settings.express_url );
		},
		supports: {
			features: settings.supports || [ 'products' ],
			showSavedCards: false,
			showSaveOption: false,
		},
	} );

	function __eh_paypal_place_order_label() {
		return settings.place_order_label
			? decodeEntities( settings.place_order_label )
			: 'Proceed to PayPal';
	}

} )();
