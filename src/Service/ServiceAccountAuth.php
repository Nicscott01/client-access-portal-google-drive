<?php

namespace ClientAccessPortalGoogleDrive\Service;

use ClientAccessPortalGoogleDrive\Support\Settings;

class ServiceAccountAuth {
	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function get_access_token() {
		$credentials = $this->load_credentials();

		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}

		$jwt = $this->build_jwt( $credentials );

		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_token_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Google token request failed: %s', 'client-access-portal-google-drive' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code || empty( $body['access_token'] ) ) {
			$error_message = is_array( $body ) && ! empty( $body['error_description'] ) ? $body['error_description'] : __( 'Unknown Google token response.', 'client-access-portal-google-drive' );

			return new \WP_Error(
				'client_access_portal_google_drive_token_invalid',
				sprintf(
					/* translators: 1: status code, 2: response message */
					__( 'Google token request returned HTTP %1$d: %2$s', 'client-access-portal-google-drive' ),
					$status_code,
					$error_message
				)
			);
		}

		return $body['access_token'];
	}

	public function load_credentials() {
		$validation = $this->settings->validate();

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$settings         = $this->settings->all();
		$credentials_path = $settings['credentials_path'];

		if ( ! file_exists( $credentials_path ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_credentials_missing',
				__( 'The Google credentials file does not exist at the configured path.', 'client-access-portal-google-drive' )
			);
		}

		if ( ! is_readable( $credentials_path ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_credentials_unreadable',
				__( 'The Google credentials file exists but is not readable by PHP.', 'client-access-portal-google-drive' )
			);
		}

		$raw         = file_get_contents( $credentials_path );
		$credentials = json_decode( (string) $raw, true );

		if ( ! is_array( $credentials ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_credentials_invalid_json',
				__( 'The Google credentials file is not valid JSON.', 'client-access-portal-google-drive' )
			);
		}

		$required_keys = array(
			'type',
			'client_email',
			'private_key',
			'token_uri',
		);

		foreach ( $required_keys as $required_key ) {
			if ( empty( $credentials[ $required_key ] ) ) {
				return new \WP_Error(
					'client_access_portal_google_drive_credentials_missing_key',
					sprintf(
						/* translators: %s: credentials key */
						__( 'The Google credentials JSON is missing the required key: %s', 'client-access-portal-google-drive' ),
						$required_key
					)
				);
			}
		}

		if ( 'service_account' !== $credentials['type'] ) {
			return new \WP_Error(
				'client_access_portal_google_drive_wrong_credentials_type',
				__( 'The credentials file is not a service account JSON key.', 'client-access-portal-google-drive' )
			);
		}

		return $credentials;
	}

	private function build_jwt( array $credentials ) {
		$issued_at = time();
		$payload   = array(
			'iss'   => $credentials['client_email'],
			'scope' => 'https://www.googleapis.com/auth/drive',
			'aud'   => $credentials['token_uri'],
			'iat'   => $issued_at,
			'exp'   => $issued_at + HOUR_IN_SECONDS,
		);

		$segments = array(
			$this->base64url_encode( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ),
			$this->base64url_encode( wp_json_encode( $payload ) ),
		);

		$signing_input = implode( '.', $segments );
		$signature     = '';
		$private_key   = openssl_pkey_get_private( $credentials['private_key'] );

		if ( false === $private_key ) {
			return new \WP_Error(
				'client_access_portal_google_drive_private_key_invalid',
				__( 'The private key in the service account JSON could not be loaded.', 'client-access-portal-google-drive' )
			);
		}

		$did_sign = openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 );

		if ( ! $did_sign ) {
			return new \WP_Error(
				'client_access_portal_google_drive_jwt_sign_failed',
				__( 'The service account JWT could not be signed with the provided private key.', 'client-access-portal-google-drive' )
			);
		}

		$segments[] = $this->base64url_encode( $signature );

		return implode( '.', $segments );
	}

	private function base64url_encode( string $input ): string {
		return rtrim( strtr( base64_encode( $input ), '+/', '-_' ), '=' );
	}
}
