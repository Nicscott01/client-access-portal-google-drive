<?php

namespace ClientAccessPortalGoogleDrive\Service;

use ClientAccessPortalGoogleDrive\Support\Settings;

class ConnectionTester {
	private Settings $settings;

	private ServiceAccountAuth $auth;

	public function __construct( Settings $settings, ServiceAccountAuth $auth ) {
		$this->settings = $settings;
		$this->auth     = $auth;
	}

	public function test() {
		$access_token = $this->auth->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings         = $this->settings->all();
		$master_folder_id = $settings['master_folder_id'];
		$url              = add_query_arg(
			array(
				'fields'            => 'id,name,mimeType,capabilities,driveId',
				'supportsAllDrives' => 'true',
			),
			'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $master_folder_id )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_folder_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Google Drive folder lookup failed: %s', 'client-access-portal-google-drive' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status_code || empty( $body['id'] ) ) {
			$error_message = is_array( $body ) && ! empty( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unknown Drive API response.', 'client-access-portal-google-drive' );

			return new \WP_Error(
				'client_access_portal_google_drive_folder_invalid',
				sprintf(
					/* translators: 1: status code, 2: response message */
					__( 'Google Drive folder lookup returned HTTP %1$d: %2$s', 'client-access-portal-google-drive' ),
					$status_code,
					$error_message
				)
			);
		}

		if ( 'application/vnd.google-apps.folder' !== ( $body['mimeType'] ?? '' ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_target_not_folder',
				__( 'The configured master folder ID resolved successfully, but it is not a Google Drive folder.', 'client-access-portal-google-drive' )
			);
		}

		return array(
			'folder_id'   => $body['id'],
			'folder_name' => $body['name'] ?? '',
		);
	}
}
