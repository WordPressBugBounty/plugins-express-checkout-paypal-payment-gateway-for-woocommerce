<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
#[\AllowDynamicProperties]
class Eh_Paypal_Express_Hooks {

	protected $eh_paypal_express_options;
	public function __construct() {
		$this->eh_paypal_express_options = get_option( 'woocommerce_eh_paypal_express_settings' );

		if ( isset( $this->eh_paypal_express_options['express_button_on_pages'] ) ) {
			$this->express_button_on_pages = $this->eh_paypal_express_options['express_button_on_pages'] ? $this->eh_paypal_express_options['express_button_on_pages'] : array();
		}

		if ( isset( $this->eh_paypal_express_options['credit_button_on_pages'] ) ) {
			$this->credit_button_on_pages = $this->eh_paypal_express_options['credit_button_on_pages'] ? $this->eh_paypal_express_options['credit_button_on_pages'] : array();
		}

		if ( isset( $this->eh_paypal_express_options['smart_button_on_pages'] ) ) {
			$this->smart_button_on_pages = $this->eh_paypal_express_options['smart_button_on_pages'] ? $this->eh_paypal_express_options['smart_button_on_pages'] : array();
		}

		if ( isset( $this->eh_paypal_express_options['smart_button_enabled'] ) && 'yes' == $this->eh_paypal_express_options['smart_button_enabled'] ) {
			add_action( 'woocommerce_review_order_after_payment', array( $this, 'eh_express_checkout_hook' ) ); // add express button in checkout page

		} else {
			add_action( 'woocommerce_before_checkout_form', array( $this, 'eh_express_checkout_hook' ) ); // add express button in checkout page
		}

		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'eh_express_checkout_hook' ), 20 );
		add_action( 'wp', array( $this, 'unset_express' ) );
		add_action( 'woocommerce_cart_emptied', array( $this, 'unset_expres_cart_empty' ) );

		add_action( 'wp_ajax_eh_smart_button_recalculate_totals',        array( $this, 'eh_smart_button_recalculate_totals' ) );
		add_action( 'wp_ajax_nopriv_eh_smart_button_recalculate_totals', array( $this, 'eh_smart_button_recalculate_totals' ) );
	}
	public function unset_express() {
		if ( ( isset( $_REQUEST['cancel_express_checkout'] ) && ( 'cancel' === $_REQUEST['cancel_express_checkout'] ) ) ) {
			if ( isset( WC()->session->eh_pe_billing ) ) {
				unset( WC()->session->eh_pe_billing );
			}
			if ( isset( WC()->session->pay_for_order['pay_for_order'] ) ) {
				unset( WC()->session->pay_for_order );
			}
			if ( isset( WC()->session->eh_pe_checkout ) ) {
				unset( WC()->session->eh_pe_checkout );
				wc_clear_notices();
				wc_add_notice( __( 'You have cancelled PayPal Express Checkout. Please try to process your order again.', 'express-checkout-paypal-payment-gateway-for-woocommerce' ), 'notice' );
			}
		}
	}
	public function unset_expres_cart_empty() {
		if ( isset( WC()->session->eh_pe_billing ) ) {
			unset( WC()->session->eh_pe_billing );
		}
		if ( isset( WC()->session->pay_for_order['pay_for_order'] ) ) {
			unset( WC()->session->pay_for_order );
		}
		if ( isset( WC()->session->eh_pe_checkout ) ) {
			unset( WC()->session->eh_pe_checkout );
		}
	}

	public function eh_smart_button_recalculate_totals() {
		check_ajax_referer( 'eh_paypal_nonce', 'nonce' );
 
		$country         = isset( $_POST['country'] )         ? wc_clean( wp_unslash( $_POST['country'] ) )         : '';
		$state           = isset( $_POST['state'] )           ? wc_clean( wp_unslash( $_POST['state'] ) )           : '';
		$postcode        = isset( $_POST['postcode'] )        ? wc_clean( wp_unslash( $_POST['postcode'] ) )        : '';
		$city            = isset( $_POST['city'] )            ? wc_clean( wp_unslash( $_POST['city'] ) )            : '';
		$paypal_order_id = isset( $_POST['paypal_order_id'] ) ? wc_clean( wp_unslash( $_POST['paypal_order_id'] ) ) : '';
 
		if ( empty( $country ) || empty( $paypal_order_id ) ) {
			wp_send_json( array( 'success' => false, 'allowed' => false, 'message' => 'Missing required parameters' ) );
			return;
		}
 
		//Country restriction check
		$wc_countries      = new WC_Countries();
		$allowed_countries = $wc_countries->get_shipping_countries();
		if ( ! empty( $allowed_countries ) && ! array_key_exists( $country, $allowed_countries ) ) {
			wp_send_json( array( 'success' => false, 'allowed' => false ) );
			return;
		}
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
 
		WC()->customer->set_shipping_country( $country );
		WC()->customer->set_shipping_state( $state );
		WC()->customer->set_shipping_postcode( $postcode );
		WC()->customer->set_shipping_city( $city );
		WC()->customer->set_billing_country( $country );
		WC()->customer->set_billing_state( $state );
		WC()->customer->set_billing_postcode( $postcode );
		WC()->customer->set_billing_city( $city );
		WC()->customer->save();
 
		//Recalculate cart totals
		WC()->session->set( 'shipping_for_package_0', null );
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();
 
		//Build updated amount breakdown
		$currency_code           = get_woocommerce_currency();
		$zero_decimal_currencies = array( 'HUF', 'JPY', 'TWD' );
		$decimals                = in_array( $currency_code, $zero_decimal_currencies, true ) ? 0 : 2;
 
		$fmt = function( $amount ) use ( $decimals ) {
			return abs( round( (float) $amount, $decimals ) );
		};
 
		$cart_total    = $fmt( WC()->cart->total );
		$cart_subtotal = $fmt( WC()->cart->subtotal_ex_tax );
		$cart_shipping = $fmt( WC()->cart->shipping_total );
		$cart_tax      = $fmt( WC()->cart->tax_total + WC()->cart->shipping_tax_total );
		$cart_discount = $fmt( abs( WC()->cart->get_cart_discount_total() ) );
		$cart_fee      = $fmt( WC()->cart->fee_total );
 
		$cart_items_total = ( $cart_subtotal + $cart_shipping + $cart_tax + $cart_fee ) - $cart_discount;
		$ship_discount    = 0;
		if ( $cart_total !== $cart_items_total ) {
			if ( $cart_items_total < $cart_total ) {
				$cart_tax += $cart_total - $cart_items_total;
			} else {
				$ship_discount = $fmt( $cart_items_total - $cart_total );
			}
		}
 
		$patch_payload = array(
			array(
				'op'    => 'replace',
				'path'  => "/purchase_units/@reference_id=='default'/amount",
				'value' => array(
					'currency_code' => $currency_code,
					'value'         => $cart_total,
					'breakdown'     => array(
						'item_total'        => array( 'currency_code' => $currency_code, 'value' => $cart_subtotal ),
						'shipping'          => array( 'currency_code' => $currency_code, 'value' => $cart_shipping ),
						'tax_total'         => array( 'currency_code' => $currency_code, 'value' => $fmt( abs( $cart_tax ) ) ),
						'discount'          => array( 'currency_code' => $currency_code, 'value' => $cart_discount ),
						'handling'          => array( 'currency_code' => $currency_code, 'value' => $cart_fee ),
						'shipping_discount' => array( 'currency_code' => $currency_code, 'value' => $ship_discount ),
					),
				),
			),
		);
 
		//Get PayPal gateway instance & access token
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways['eh_paypal_express'] ) ) {
			wp_send_json( array( 'success' => false, 'message' => 'Gateway not available' ) );
			return;
		}
		$gateway = $gateways['eh_paypal_express'];
 
		$request_process = new Eh_PE_Process_Request();
		$request_build   = $gateway->new_rest_request();
		$access_token    = $gateway->get_access_token( $request_process, $request_build, false );
 
		if ( ! $access_token ) {
			wp_send_json( array( 'success' => false, 'message' => 'Could not obtain access token' ) );
			return;
		}
 
		//PATCH the PayPal order with the updated amount
		ini_set( 'precision', 14 );
		ini_set( 'serialize_precision', -1 );
 
		$headers = array(
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		);
 
		$patch_args = array(
			'method'      => 'PATCH',
			'timeout'     => 30,
			'redirection' => 0,
			'httpversion' => '1.1',
			'sslverify'   => false,
			'blocking'    => true,
			'headers'     => $headers,
			'body'        => wp_json_encode( $patch_payload ),
			'cookies'     => array(),
		);
		$patch_args = apply_filters( 'wt_paypal_http_request', $patch_args );
 
		Eh_PayPal_Log::log_update(
			wp_json_encode( $patch_payload, JSON_PRETTY_PRINT ),
			'Smart Button onShippingChange PATCH Request',
			'json'
		);
 
		$response  = wp_safe_remote_request(
			$gateway->rest_api_url . '/v2/checkout/orders/' . $paypal_order_id,
			$patch_args
		);
 
		if ( is_wp_error( $response ) ) {
			Eh_PayPal_Log::log_update(
				$response->get_error_message(),
				'Smart Button onShippingChange PATCH WP_Error',
				'json'
			);
			wp_send_json( array( 'success' => false, 'message' => $response->get_error_message() ) );
			return;
		}
 
		$http_code = wp_remote_retrieve_response_code( $response );
		Eh_PayPal_Log::log_update(
			'HTTP ' . $http_code,
			'Smart Button onShippingChange PATCH Response',
			'json'
		);
 
		// PayPal returns HTTP 204 No Content on a successful PATCH
		wp_send_json( array( 'success' => ( '204' == $http_code ) ) );
	}
	
	public function express_run() {
		if ( isset( $this->eh_paypal_express_options['enabled'] ) && ( 'yes' === $this->eh_paypal_express_options['enabled'] ) ) {
			$this->check_express();
		}
	}
	protected function check_express() {
		if ( isset( WC()->session->eh_pe_checkout ) ) {
			return;
		}

		if ( is_cart() ) {
			$show_button_in_cart_page = apply_filters( 'wt_show_paypal_express_button_in_cart_page', true );
			if ( $show_button_in_cart_page ) {
				$this->checkout_button_include();
				$this->eh_payment_scripts();
			}
		}
		if ( is_checkout() ) {
			$show_button_in_checkout_page = apply_filters( 'wt_show_paypal_express_button_in_checkout_page', true );
			if ( $show_button_in_checkout_page ) {
				$this->checkout_button_include();
				$this->eh_payment_scripts();
			}
		}

	}
	public function eh_express_checkout_hook() {
		if ( apply_filters( 'eh_hide_paypal_express_button_in_cart', false ) ) {
			return;
		}
		require_once EH_PAYPAL_MAIN_PATH . 'includes/functions.php';
		eh_paypal_express_hook_init();
	}
	public function checkout_button_include() {
		$page = get_permalink();

		// Smart button
		if ( isset( $this->eh_paypal_express_options['smart_button_enabled'] ) && 'yes' == $this->eh_paypal_express_options['smart_button_enabled'] ) {
			$desc  = '';
			$style = '';
			print wp_kses_post( '<span class="eh_spinner" style="display:none"><img style="width:50px;"  src="' . admin_url() . 'images/loading.gif" /></span>' );
			if ( isset( $this->eh_paypal_express_options['smart_button_description'] ) && '' !== $this->eh_paypal_express_options['smart_button_description'] ) {
				$desc = '<div class="eh_paypal_express_description" ><small>-- ' . $this->eh_paypal_express_options['smart_button_description'] . ' --</small></div>';
			}
			$ex_button_output = '<center>';
			if ( $this->eh_express_button_enabled() ) {
				if ( isset( $this->eh_paypal_express_options['smart_button_size'] ) ) {
					if ( 'small' == $this->eh_paypal_express_options['smart_button_size'] ) {
						$style = 'style="width:35%"';
					} elseif ( 'medium' == $this->eh_paypal_express_options['smart_button_size'] ) {
						$style = 'style="width:45%"';
					} elseif ( 'large' == $this->eh_paypal_express_options['smart_button_size'] ) {
						$style = 'style="width:55%"';
					}
				}
				$style             = apply_filters( 'eh_paypal_smart_button_style', $style );
				$ex_button_output .= $desc . '<div ' . $style . ' class="single_add_to_cart_button eh_paypal_express_link" id="paypal-checkout-button-render"></div>';

				$ex_button_output .= '</center>';
				echo wp_kses_post( $ex_button_output );
			}
		}
		// Express checkout
		else {
			$express_button   = apply_filters( 'eh_paypal_express_checkout_button', EH_PAYPAL_MAIN_URL . 'assets/img/checkout-' . $this->eh_paypal_express_options['button_size'] . '.svg' );
			$express_ccbutton = apply_filters( 'eh_paypal_express_checkout_ccbutton', EH_PAYPAL_MAIN_URL . 'assets/img/paypalcredit-' . $this->eh_paypal_express_options['button_size'] . '.svg' );

			$desc     = '';
			$add_desc = 0;
			if ( '' !== $this->eh_paypal_express_options['express_description'] ) {
				$desc = '<div class="eh_paypal_express_description" ><small>-- ' . $this->eh_paypal_express_options['express_description'] . ' --</small></div>';
			}
			$ex_button_output = '<center>';

			if ( $this->eh_express_button_enabled() ) {
				$ex_button_output .= $desc . '<a href="' . esc_url( add_query_arg( 'p', $page, $this->make_express_url( 'express_start' ) ) ) . '" class="single_add_to_cart_button eh_paypal_express_link"><img src="' . $express_button . '" style="width:auto;height:auto;" class=" single_add_to_cart_button eh_paypal_express_image" alt="' . __( 'Check out with PayPal', 'express-checkout-paypal-payment-gateway-for-woocommerce' ) . '" /></a>';
			} else {
				$add_desc = 1;
			}

			if ( $this->eh_express_credit_button_enabled() ) {
				if ( 1 == $add_desc ) {
					$ex_button_output .= $desc;
				}
				$ex_button_output .= '<a href="' . esc_url( add_query_arg( 'p', $page, $this->make_express_url( 'credit_start' ) ) ) . '" class="single_add_to_cart_button eh_paypal_express_link"><img src="' . $express_ccbutton . '" style="width:auto;height:auto;" class=" single_add_to_cart_button eh_paypal_express_image" alt="' . __( 'Check out with PayPal Credit', 'express-checkout-paypal-payment-gateway-for-woocommerce' ) . '" /></a>';
			}

			$ex_button_output .= '</center>';
			echo wp_kses_post( $ex_button_output );
		}
	}

	public function eh_express_button_enabled() {

        if ( class_exists( 'WC_Subscriptions_Cart' ) && function_exists( 'wcs_cart_contains_renewal' ) ) {
            // Needs a billing agreement if the cart contains a subscription
            if(WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal()){
            	return false;
            }


        }

		if ( isset( $this->eh_paypal_express_options['smart_button_enabled'] ) && 'yes' == $this->eh_paypal_express_options['smart_button_enabled'] && 'yes' == $this->eh_paypal_express_options['enabled'] ) {
			if ( is_cart() ) {
				if ( ( isset( $this->smart_button_on_pages ) && in_array( 'cart', $this->smart_button_on_pages ) ) ) {
					return true;
				}
			}
			if ( is_checkout() ) {
				if ( ( isset( $this->smart_button_on_pages ) && in_array( 'checkout', $this->smart_button_on_pages ) ) ) {
					return true;
				}
			}

			return false;
		} else {
			if ( is_cart() ) {
				if ( ( isset( $this->express_button_on_pages ) && in_array( 'cart', $this->express_button_on_pages ) ) || ( isset( $this->eh_paypal_express_options['express_enabled'] ) && ( 'yes' === $this->eh_paypal_express_options['express_enabled'] ) && isset( $this->eh_paypal_express_options['express_on_cart_page'] ) && 'yes' === $this->eh_paypal_express_options['express_on_cart_page'] ) ) {
					return true;
				}
			}
			if ( is_checkout() ) {
				if ( ( isset( $this->express_button_on_pages ) && in_array( 'checkout', $this->express_button_on_pages ) ) || ( isset( $this->eh_paypal_express_options['express_enabled'] ) && ( 'yes' === $this->eh_paypal_express_options['express_enabled'] ) && isset( $this->eh_paypal_express_options['express_on_checkout_page'] ) && 'yes' === $this->eh_paypal_express_options['express_on_checkout_page'] ) ) {
					return true;
				}
			}
			return false;
		}
	}
	public function eh_express_credit_button_enabled() {
        if ( class_exists( 'WC_Subscriptions_Cart' ) && function_exists( 'wcs_cart_contains_renewal' ) ) {
            // Needs a billing agreement if the cart contains a subscription
            if(WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal()){
            	return false;
            }

        }

		if ( is_cart() ) {
			if ( ( isset( $this->credit_button_on_pages ) && in_array( 'cart', $this->credit_button_on_pages ) ) || ( isset( $this->eh_paypal_express_options['express_enabled'] ) && ( 'yes' === $this->eh_paypal_express_options['express_enabled'] ) && isset( $this->eh_paypal_express_options['credit_checkout'] ) && ( 'yes' === $this->eh_paypal_express_options['credit_checkout'] ) && isset( $this->eh_paypal_express_options['express_on_cart_page'] ) && 'yes' === $this->eh_paypal_express_options['express_on_cart_page'] ) ) {
				return true;
			}
		}
		if ( is_checkout() ) {
			if ( ( isset( $this->credit_button_on_pages ) && in_array( 'checkout', $this->credit_button_on_pages ) ) || ( isset( $this->eh_paypal_express_options['express_enabled'] ) && ( 'yes' === $this->eh_paypal_express_options['express_enabled'] ) && isset( $this->eh_paypal_express_options['credit_checkout'] ) && ( 'yes' === $this->eh_paypal_express_options['credit_checkout'] ) && isset( $this->eh_paypal_express_options['express_on_checkout_page'] ) && 'yes' === $this->eh_paypal_express_options['express_on_checkout_page'] ) ) {
				return true;
			}
		}
		return false;

	}
	public function make_express_url( $action ) {

		return add_query_arg( 'c', $action, WC()->api_request_url( 'Eh_PayPal_Express_Payment' ) );
	}
	public function eh_payment_scripts() {
		if ( is_cart() || is_checkout() ) {
			$page              = get_permalink();
			$pagename          = ( is_cart() ) ? 'cart' : 'checkout';
			$wpc_plugin_active = is_plugin_active( 'wpc-ajax-add-to-cart/wpc-ajax-add-to-cart.php' ) ? 'yes' : 'no';
			wp_register_style( 'eh-express-style', EH_PAYPAL_MAIN_URL . 'assets/css/eh-express-style.css', array(), EH_PAYPAL_VERSION );
			wp_enqueue_style( 'eh-express-style' );
			wp_register_script( 'eh-express-js', EH_PAYPAL_MAIN_URL . 'assets/js/eh-express-script.js', array(), EH_PAYPAL_VERSION );
			wp_enqueue_script( 'eh-express-js' );
			wp_localize_script(
				'eh-express-js',
				'eh_express_checkout_params',
				array(
					'page_name'  => $pagename,
					'wpc_plugin' => $wpc_plugin_active,
				)
			);

			if ( isset( $this->eh_paypal_express_options['smart_button_enabled'] ) && 'yes' == $this->eh_paypal_express_options['smart_button_enabled'] ) {
				if ( 'live' == $this->eh_paypal_express_options['smart_button_environment'] ) {
					$client_id = ( isset( $this->eh_paypal_express_options['live_client_id'] ) ? $this->eh_paypal_express_options['live_client_id'] : '' );
				} else {
					 $client_id = ( isset( $this->eh_paypal_express_options['sandbox_client_id'] ) ? $this->eh_paypal_express_options['sandbox_client_id'] : '' );
				}
				$smart_payment_url = esc_url(
					add_query_arg(
						array(
							'p'    => $page,
							'type' => 'ajax',
						),
						$this->make_express_url( 'create_order' )
					)
				);
				$c                 = 'create_order';
				$intent            = 'capture';
				$locale            = ( 'yes' === $this->eh_paypal_express_options['smart_button_paypal_locale'] ) ? $this->get_locale( get_locale() ) : false;
				$commit            = ( 'yes' === $this->eh_paypal_express_options['smart_button_skip_review'] ? 'true' : 'false' );

				 $paypal_script_url = esc_url(
					add_query_arg(
						array(
							'client-id'  => $client_id,
							'intent'     => $intent,
							'currency'   => get_woocommerce_currency(),
							'locale'     => $locale,
							'commit'     => $commit,
							'components' => 'buttons',
							'debug'      => 'false',
						),
						'https://www.paypal.com/sdk/js'
					)
				);

				// Funding sources supported by paypal
				$supported_payment_methods = array( 'paylater', 'venmo' );

				if ( isset( $this->eh_paypal_express_options['disable_funding_source'] ) && ! empty( $this->eh_paypal_express_options['disable_funding_source'] ) ) {

					$funding_sources_disabled = $this->eh_paypal_express_options['disable_funding_source'];
					// get the funding sources except the disabled ones
					$enabled_funding_sources = array_diff( $supported_payment_methods, $funding_sources_disabled );

					if ( isset( $enabled_funding_sources ) && ! empty( $enabled_funding_sources ) ) {
						// array to string conversion
						$enabled_funding = implode( ',', $enabled_funding_sources );
						// enable fundings except the disabled one
						$paypal_script_url .= '&enable-funding=' . $enabled_funding;
					}

					$disabled_funding = implode( ',', $funding_sources_disabled );
					if ( ! empty( $disabled_funding ) ) {
						$paypal_script_url .= '&disable-funding=' . $disabled_funding;
					}
				} else {
					// if there is no disabled funding, then enable all supported  fundings by PayPal
					 $enabled_funding   = implode( ',', $supported_payment_methods );
					$paypal_script_url .= '&enable-funding=' . $enabled_funding;
				}

				$paypal_script_url = apply_filters( 'wt_paypal_alter_script_url', $paypal_script_url );
				$button_params     = array(
					'c'              => 'express_start',
					'p'              => $page,
					'express_button' => true,
					'return_url'     => add_query_arg(
						array(
							'p'      => $page,
							'intent' => $intent,
							'type'   => 'ajax',
						),
						$this->make_express_url( 'order_details' )
					),
					'cancel_url'     => add_query_arg( 'p', $page, $this->make_express_url( 'cancel_order' ) ),
					'express_url'    => $smart_payment_url,
					'environment'    => $this->eh_paypal_express_options['smart_button_environment'],
					'size'           => $this->eh_paypal_express_options['smart_button_size'],
					'layout'         => $this->eh_paypal_express_options['button_layout'],
					'color'          => $this->eh_paypal_express_options['button_color'],
					'shape'          => $this->eh_paypal_express_options['button_shape'],
					'label'          => $this->eh_paypal_express_options['button_label'],
					'tagline'        => $this->eh_paypal_express_options['button_tagline'],
					'locale'         => $locale,
					'page_name'      => $pagename,
					'ajax_url'       => admin_url( 'admin-ajax.php' ),
					'nonce'          => wp_create_nonce( 'eh_paypal_nonce' ),
				);

				wp_register_script( 'paypal-checkout-incontext-js', $paypal_script_url, array(), null);
				wp_register_script( 'eh-smart-button-js', EH_PAYPAL_MAIN_URL . 'assets/js/eh-button-render.js', array( 'paypal-checkout-incontext-js' ), EH_PAYPAL_VERSION );
				wp_enqueue_script( 'eh-smart-button-js' );
				wp_localize_script( 'eh-smart-button-js', 'eh_button_params', $button_params );
			}
		}
	}

	public function get_locale( $locale ) {
		$safe_locales = array(
			'da_DK',
			'de_DE',
			'en_AU',
			'en_GB',
			'en_US',
			'es_ES',
			'fr_CA',
			'fr_FR',
			'he_IL',
			'id_ID',
			'it_IT',
			'ja_JP',
			'nl_NL',
			'pl_PL',
			'pt_BR',
			'pt_PT',
			'ru_RU',
			'sv_SE',
			'th_TH',
			'tr_TR',
			'zh_CN',
			'zh_HK',
			'zh_TW',
		);
		if ( ! in_array( $locale, $safe_locales ) ) {
			$locale = 'en_US';
		}
		return $locale;
	}
}
