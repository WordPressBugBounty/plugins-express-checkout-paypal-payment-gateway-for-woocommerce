<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Eh_PayPal_Log {

	public static function init_log() {
		$content = '<------------------- ThemeHigh PayPal Express Payment Log File ( ' . EH_PAYPAL_VERSION . " ) ------------------->\n";
		return $content;
	}

	public static function remove_data( $incoming_data ) {
		$data = (array) $incoming_data;

		$show_sensitive = true === apply_filters( 'wt_paypal_show_sensitive_data', false );
		$sensitive_keys = array( 'USER', 'PWD', 'SIGNATURE', 'VERSION', 'client_id', 'client_secret', 'access_token' );
		foreach ( $data as $key => $value ) {
			// Never log HTTP authentication headers, including encoded credentials.
			if ( 'authorization' === strtolower( (string) $key ) || ( ! $show_sensitive && in_array( $key, $sensitive_keys, true ) ) ) {
				unset( $data[ $key ] );
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = self::remove_data( $value );
			}
		}
		return $data;
	}

	public static function log_update( $mg, $title, $type = null ) {
		$check = get_option( 'woocommerce_eh_paypal_express_settings' );
		if ( 'json' == $type ) {
			$resp = self::remove_data( json_decode( $mg, true ) );
			$msg  = wp_json_encode( $resp, JSON_PRETTY_PRINT );
		} else {
			$msg = self::remove_data( $mg );
		}
		if ( 'yes' === $check['paypal_logging'] ) {
			if ( version_compare(WC()->version, '2.7.0', '>=') ) {
				$log  = wc_get_logger();
				$head = '<------------------- ThemeHigh PayPal Express Payment ( ' . $title . " ) ------------------->\n";
				if ( 'json' == $type ) {
					$log_text = $head . print_r( $msg, true );
				} else {
					 $log_text = $head . print_r( (object) $msg, true );
				}
				$context = array( 'source' => 'eh_paypal_express_log' );
				$log->log( 'debug', $log_text, $context );
			} else {
				$log  = new WC_Logger();
				$head = '<------------------- ThemeHigh PayPal Express Payment ( ' . $title . " ) ------------------->\n";
				if ( 'json' == $type ) {
					$log_text = $head . print_r( $msg, true );
				} else {
					$log_text = $head . print_r( (object) $msg, true );
				}                $log->add( 'eh_paypal_express_log', $log_text );
			}
		}
	}

}
