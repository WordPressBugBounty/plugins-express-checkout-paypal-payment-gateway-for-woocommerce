<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
#[\AllowDynamicProperties]
class Eh_PE_Process_Request {

	public function process_request( $params, $uri, $is_rest = false ) {
		$response_processer = new Eh_PE_Process_Response();
		$response_processer->is_rest = $is_rest;
		$response           = $response_processer->process_response( wp_safe_remote_request( $uri, $params ) );
		return $response;
	}

}
