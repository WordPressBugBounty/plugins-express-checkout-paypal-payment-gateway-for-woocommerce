<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
#[\AllowDynamicProperties]
class Eh_PE_Process_Response {

	public $is_rest = false;

	public function process_response( $response ) {
		if ( isset( $response->errors['http_request_failed'] ) ) {
			$this->response = $response->errors;
		} elseif ( isset( $response->errors['http_failure'] ) ) {
			$this->response = $response->errors;
		} else {
			$this->response = $this->parse_response( $response );
		}
		return $this->response;
	}
	public function parse_response( $response ) {
		$parsed_response = '';
		if ( is_wp_error( $response ) ) {
			return;
		}
		if ( $this->is_rest ) {
			$parsed_response = json_decode( ( wp_remote_retrieve_body( $response ) ), true );
			if ( empty( $parsed_response ) ) {
				$parsed_response = wp_remote_retrieve_response_code( $response );
			}
		} else {
			parse_str( $response['body'], $parsed_response );
		}
		return $parsed_response;
	}
}
