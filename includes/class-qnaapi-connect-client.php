<?php
/**
 * Thin wrapper around the QNAAPI REST API (https://qnaapi.com/docs) using
 * WordPress's own HTTP API. Every management call (create/read/update/delete
 * on polls, quizzes, forms) is authenticated with the account's X-API-Key;
 * the public vote/attempt/submission endpoints never send that header.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class QNAAPI_Connect_Client {

	/** @var string */
	private $api_key;

	/** @var string */
	private $base_url;

	public function __construct( $api_key, $base_url ) {
		$this->api_key  = $api_key;
		$this->base_url = untrailingslashit( $base_url );
	}

	public function has_api_key() {
		return '' !== trim( (string) $this->api_key );
	}

	public function get( $path, $args = array() ) {
		return $this->request( 'GET', $path, $args );
	}

	public function post( $path, $body = array() ) {
		return $this->request( 'POST', $path, array(), $body );
	}

	public function patch( $path, $body = array() ) {
		return $this->request( 'PATCH', $path, array(), $body );
	}

	public function delete( $path ) {
		return $this->request( 'DELETE', $path );
	}

	/**
	 * @return array|WP_Error {
	 *     @type int   $status
	 *     @type array $body Decoded JSON body (empty array for 204s).
	 * }
	 */
	private function request( $method, $path, $query = array(), $body = null ) {
		if ( ! $this->has_api_key() ) {
			return new WP_Error( 'qnaapi_connect_no_api_key', __( 'No QNAAPI API key is configured. Add one under Settings → QNAAPI Connect.', 'qnaapi-connect' ) );
		}

		$url = $this->base_url . '/' . ltrim( $path, '/' );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$request_args = array(
			'method'  => $method,
			'headers' => array(
				'X-API-Key' => $this->api_key,
				'Accept'    => 'application/json',
			),
			'timeout' => 15,
		);

		if ( null !== $body ) {
			$request_args['headers']['Content-Type'] = 'application/json';
			$request_args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status       = wp_remote_retrieve_response_code( $response );
		$raw_body     = wp_remote_retrieve_body( $response );
		$decoded_body = strlen( trim( $raw_body ) ) ? json_decode( $raw_body, true ) : array();

		if ( null === $decoded_body ) {
			$decoded_body = array();
		}

		if ( $status >= 400 ) {
			$message = isset( $decoded_body['message'] ) ? $decoded_body['message'] : __( 'The QNAAPI request failed.', 'qnaapi-connect' );

			return new WP_Error(
				'qnaapi_connect_api_error',
				$message,
				array(
					'status' => $status,
					'errors' => isset( $decoded_body['errors'] ) ? $decoded_body['errors'] : array(),
				)
			);
		}

		return array(
			'status' => $status,
			'body'   => $decoded_body,
		);
	}
}
