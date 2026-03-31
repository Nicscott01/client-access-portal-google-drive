<?php

namespace ClientAccessPortalGoogleDrive\Service;

use ClientAccessPortalGoogleDrive\Support\Settings;

class DriveService {
	private Settings $settings;

	private ServiceAccountAuth $auth;

	public function __construct( Settings $settings, ServiceAccountAuth $auth ) {
		$this->settings = $settings;
		$this->auth     = $auth;
	}

	public function create_folder( string $name, string $parent_id ) {
		return $this->request(
			'POST',
			'https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id,name,mimeType,webViewLink,parents',
			array(
				'headers' => array(
					'Content-Type' => 'application/json; charset=UTF-8',
				),
				'body'    => wp_json_encode(
					array(
						'name'     => $name,
						'mimeType' => 'application/vnd.google-apps.folder',
						'parents'  => array( $parent_id ),
					)
				),
			)
		);
	}

	public function list_children( string $folder_id ) {
		$query = add_query_arg(
			array(
				'q'                 => sprintf( "'%s' in parents and trashed = false", $folder_id ),
				'fields'            => 'files(id,name,mimeType,size,modifiedTime,webViewLink,iconLink,parents)',
				'orderBy'           => 'folder,name',
				'supportsAllDrives' => 'true',
				'includeItemsFromAllDrives' => 'true',
			),
			'https://www.googleapis.com/drive/v3/files'
		);

		$result = $this->request( 'GET', $query );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return is_array( $result['files'] ?? null ) ? $result['files'] : array();
	}

	public function get_file( string $file_id ) {
		$url = add_query_arg(
			array(
				'fields' => 'id,name,mimeType,size,modifiedTime,webViewLink,iconLink,parents',
				'supportsAllDrives' => 'true',
			),
			'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id )
		);

		return $this->request( 'GET', $url );
	}

	public function download_file( string $file_id ) {
		$url = add_query_arg(
			array(
				'alt'               => 'media',
				'supportsAllDrives' => 'true',
			),
			'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id )
		);

		return $this->request_raw( 'GET', $url );
	}

	public function upload_file( string $parent_id, array $upload ) {
		if ( empty( $upload['tmp_name'] ) || ! file_exists( $upload['tmp_name'] ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_upload_tmp',
				__( 'The uploaded file could not be found on the server.', 'client-access-portal-google-drive' )
			);
		}

		$file_contents = file_get_contents( $upload['tmp_name'] );

		if ( false === $file_contents ) {
			return new \WP_Error(
				'client_access_portal_google_drive_upload_read_failed',
				__( 'The uploaded file could not be read before transfer to Google Drive.', 'client-access-portal-google-drive' )
			);
		}

		$mime_type = ! empty( $upload['type'] ) ? $upload['type'] : 'application/octet-stream';
		$boundary  = 'client-access-portal-' . wp_generate_password( 12, false, false );
		$metadata  = wp_json_encode(
			array(
				'name'    => sanitize_file_name( $upload['name'] ),
				'parents' => array( $parent_id ),
			)
		);

		$body  = "--{$boundary}\r\n";
		$body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
		$body .= $metadata . "\r\n";
		$body .= "--{$boundary}\r\n";
		$body .= "Content-Type: {$mime_type}\r\n\r\n";
		$body .= $file_contents . "\r\n";
		$body .= "--{$boundary}--";

		return $this->request(
			'POST',
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,mimeType,size,modifiedTime,webViewLink,parents',
			array(
				'headers' => array(
					'Content-Type' => 'multipart/related; boundary=' . $boundary,
				),
				'body'    => $body,
				'timeout' => 60,
			)
		);
	}

	private function request( string $method, string $url, array $args = array() ) {
		$token = $this->auth->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = wp_parse_args(
			$args,
			array(
				'timeout' => 20,
				'headers' => array(),
			)
		);

		$args['method']                 = $method;
		$args['headers']['Authorization'] = 'Bearer ' . $token;
		$response                       = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = is_array( $body ) && ! empty( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unknown Google Drive API response.', 'client-access-portal-google-drive' );

			return new \WP_Error(
				'client_access_portal_google_drive_api_error',
				sprintf(
					/* translators: 1: status code, 2: response message */
					__( 'Google Drive API returned HTTP %1$d: %2$s', 'client-access-portal-google-drive' ),
					$status_code,
					$error_message
				)
			);
		}

		return is_array( $body ) ? $body : array();
	}

	private function request_raw( string $method, string $url, array $args = array() ) {
		$token = $this->auth->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = wp_parse_args(
			$args,
			array(
				'timeout' => 60,
				'headers' => array(),
			)
		);

		$args['method']                   = $method;
		$args['headers']['Authorization'] = 'Bearer ' . $token;
		$response                         = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_message = is_array( $body ) && ! empty( $body['error']['message'] ) ? $body['error']['message'] : __( 'Unknown Google Drive download response.', 'client-access-portal-google-drive' );

			return new \WP_Error(
				'client_access_portal_google_drive_download_error',
				sprintf(
					/* translators: 1: status code, 2: response message */
					__( 'Google Drive download returned HTTP %1$d: %2$s', 'client-access-portal-google-drive' ),
					$status_code,
					$error_message
				)
			);
		}

		return array(
			'headers' => wp_remote_retrieve_headers( $response ),
			'body'    => wp_remote_retrieve_body( $response ),
		);
	}
}
