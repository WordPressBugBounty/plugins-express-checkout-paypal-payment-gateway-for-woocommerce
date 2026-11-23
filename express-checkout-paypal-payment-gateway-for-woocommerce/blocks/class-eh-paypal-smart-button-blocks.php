<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registers the PayPal Smart Button as an Express Payment Method for the
 * WooCommerce Cart and Checkout blocks.
 */

final class EH_PayPal_Smart_Button_Blocks extends AbstractPaymentMethodType {

    protected $name = 'eh_paypal_express';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_eh_paypal_express_settings', array() );
    }

    public function is_active() {
        if ( 'yes' !== ( $this->settings['enabled'] ?? 'no' ) ) {
            return false;
        }

        if ( 'yes' !== ( $this->settings['smart_button_enabled'] ?? 'no' ) ) {
            return false;
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway  = $gateways[ $this->name ] ?? null;

        return $gateway && $gateway->is_available();
    }

    public function get_supported_features() {
        return array(
            'products',
        );
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'eh-paypal-smart-button-block',
            EH_PAYPAL_MAIN_URL . 'blocks/js/eh-paypal-smart-button-block.js',
            array( 'wc-blocks-registry', 'wc-blocks-data-store', 'wc-settings', 'wp-data', 'wp-element', 'wp-html-entities' ),
            EH_PAYPAL_VERSION,
            true
        );

        return array( 'eh-paypal-smart-button-block' );
    }

    public function get_payment_method_data() {
        if ( ! $this->is_active() ) {
            return array();
        }

        $options = $this->settings;
        $page    = get_permalink();

        $environment = ( 'live' === ( $options['smart_button_environment'] ?? '' ) ) ? 'live' : 'sandbox';
        $client_id   = ( 'live' === $environment )
            ? ( $options['live_client_id'] ?? '' )
            : ( $options['sandbox_client_id'] ?? '' );

        $intent   = 'capture';
        $currency = get_woocommerce_currency();

        $hooks  = ( function_exists( 'eh_paypal_express_run' ) && isset( eh_paypal_express_run()->hook_include ) )
            ? eh_paypal_express_run()->hook_include
            : null;

        $locale = ( 'yes' === ( $options['smart_button_paypal_locale'] ?? '' ) )
            ? ( $hooks ? $hooks->get_locale( get_locale() ) : get_locale() )
            : false;

        $commit = ( 'yes' === ( $options['smart_button_skip_review'] ?? '' ) ) ? 'true' : 'false';

        $funding_sources_disabled = ( isset( $options['disable_funding_source'] ) && ! empty( $options['disable_funding_source'] ) )
            ? (array) $options['disable_funding_source']
            : array();

        $bancontact_enabled = ( 'EUR' === $currency && ! in_array( 'bancontact', $funding_sources_disabled, true ) );
        $ideal_enabled = ( 'EUR' === $currency && ! in_array( 'ideal', $funding_sources_disabled, true ) );
        $blik_enabled = ( 'PLN' === $currency && ! in_array( 'blik', $funding_sources_disabled, true ) );
        $components = ( $bancontact_enabled || $ideal_enabled || $blik_enabled ) ? 'buttons,payment-fields,marks,funding-eligibility' : 'buttons';

        $sdk_args = array(
            'client-id'  => $client_id,
            'intent'     => $intent,
            'currency'   => $currency,
            'locale'     => $locale,
            'commit'     => $commit,
            'components' => $components,
            'debug'      => 'false',
        );

        $paypal_script_url = esc_url_raw( add_query_arg( $sdk_args, 'https://www.paypal.com/sdk/js' ) );

        $supported_payment_methods = array( 'paylater', 'venmo' );
        if ( $bancontact_enabled ) {
            $supported_payment_methods[] = 'bancontact';
        }
        if ( $ideal_enabled ) {
            $supported_payment_methods[] = 'ideal';
        }

        if ( $blik_enabled ) {
            $supported_payment_methods[] = 'blik';
        }

        $enabled_funding_sources = array_diff( $supported_payment_methods, $funding_sources_disabled );
        if ( ! empty( $enabled_funding_sources ) ) {
            $paypal_script_url .= '&enable-funding=' . implode( ',', $enabled_funding_sources );
        }
        if ( ! empty( $funding_sources_disabled ) ) {
            $paypal_script_url .= '&disable-funding=' . implode( ',', $funding_sources_disabled );
        }

        $paypal_script_url = apply_filters( 'wt_paypal_alter_script_url', $paypal_script_url );

        $make_url = function ( $action ) use ( $hooks ) {
            if ( $hooks ) {
                return $hooks->make_express_url( $action );
            }
            return add_query_arg( 'c', $action, WC()->api_request_url( 'Eh_PayPal_Express_Payment' ) );
        };

        $container_style_attr = '';
        if ( isset( $options['smart_button_size'] ) ) {
            if ( 'small' === $options['smart_button_size'] ) {
                $container_style_attr = 'width:35%';
            } elseif ( 'medium' === $options['smart_button_size'] ) {
                $container_style_attr = 'width:45%';
            } elseif ( 'large' === $options['smart_button_size'] ) {
                $container_style_attr = 'width:55%';
            }
        }
        $container_style_attr = apply_filters( 'eh_paypal_smart_button_style', $container_style_attr );

        return array(
            'title'                 => $options['smart_button_title'] ?? __( 'PayPal', 'express-checkout-paypal-payment-gateway-for-woocommerce' ),
            'description'           => $options['smart_button_description'] ?? '',
            'gateway_description'   => $options['smart_button_gateway_description'] ?? '',
            'place_order_label'     => $this->is_in_paypal_review_session()
                ? __( 'Place Order', 'express-checkout-paypal-payment-gateway-for-woocommerce' )
                : __( 'Proceed to PayPal', 'express-checkout-paypal-payment-gateway-for-woocommerce' ),
            'sdk_url'               => $paypal_script_url,
            'express_url'           => esc_url_raw( add_query_arg( array( 'p' => $page, 'type' => 'ajax' ), $make_url( 'create_order' ) ) ),
            'is_checkout'           => is_checkout(),
            'checkout_validation_error' => __( 'Please check the highlighted checkout fields.', 'express-checkout-paypal-payment-gateway-for-woocommerce' ),
            'return_url'            => esc_url_raw( add_query_arg( array( 'p' => $page, 'intent' => $intent, 'type' => 'ajax' ), $make_url( 'order_details' ) ) ),
            'cancel_url'            => esc_url_raw( add_query_arg( 'p', $page, $make_url( 'cancel_order' ) ) ),
            'size'                  => $options['smart_button_size'] ?? 'responsive',
            'container_style_attr'  => $container_style_attr,
            'layout'                => $options['button_layout'] ?? 'vertical',
            'color'                 => $options['button_color'] ?? 'gold',
            'shape'                 => $options['button_shape'] ?? 'rect',
            'label'                 => $options['button_label'] ?? 'checkout',
            'tagline'               => $options['button_tagline'] ?? 'show',
            'locale'                => $locale,
            'bancontact_enabled'    => $bancontact_enabled ? 'yes' : 'no',
            'ideal_enabled'         => $ideal_enabled ? 'yes' : 'no',
            'blik_enabled'          => $blik_enabled ? 'yes' : 'no',
            'ajax_url'              => admin_url( 'admin-ajax.php' ),
            'nonce'                 => wp_create_nonce( 'eh_paypal_nonce' ),
            'supports'              => $this->get_supported_features(),
            'hide_express_widget'   => ( ! $this->express_widget_allowed_on_current_page() || $this->is_in_paypal_review_session() ) ? 'yes' : 'no',
        );
    }

    private function express_widget_allowed_on_current_page() {
        $on_pages = ( isset( $this->settings['smart_button_on_pages'] ) && is_array( $this->settings['smart_button_on_pages'] ) )
            ? $this->settings['smart_button_on_pages']
            : array();

        if ( function_exists( 'is_checkout' ) && is_checkout() && ! in_array( 'checkout', $on_pages, true ) ) {
            return false;
        }

        if ( function_exists( 'is_cart' ) && is_cart() && ! in_array( 'cart', $on_pages, true ) ) {
            return false;
        }

        return true;
    }

    private function is_in_paypal_review_session() {
        return function_exists( 'WC' ) && WC()->session && isset( WC()->session->eh_pe_checkout );
    }
}
