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
				'fields'            => 'files(id,name,mimeType,size,modifiedTime,webViewLink,iconLink,thumbnailLink,parents,description)',
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
				'fields' => 'id,name,mimeType,size,modifiedTime,webViewLink,iconLink,thumbnailLink,parents,description',
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

	public function upload_file( string $parent_id, array $upload, string $note = '' ) {
		if ( empty( $upload['tmp_name'] ) || ! file_exists( $upload['tmp_name'] ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_upload_tmp',
				__( 'The uploaded file could not be found on the server.', 'client-access-portal-google-drive' )
			);
		}

		$file_size = filesize( $upload['tmp_name'] );

		if ( false !== $file_size && $file_size > 8 * MB_IN_BYTES ) {
			return $this->upload_file_resumable( $parent_id, $upload, $note, (int) $file_size );
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
				'description' => $note,
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
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,mimeType,size,modifiedTime,webViewLink,iconLink,thumbnailLink,parents,description',
			array(
				'headers' => array(
					'Content-Type' => 'multipart/related; boundary=' . $boundary,
				),
				'body'    => $body,
				'timeout' => 60,
			)
		);
	}

	public function create_resumable_upload_session( string $parent_id, array $file, string $note = '' ) {
		$token = $this->auth->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$file_name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
		$file_type = ! empty( $file['type'] ) ? (string) $file['type'] : 'application/octet-stream';
		$file_size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( '' === $file_name || $file_size <= 0 ) {
			return new \WP_Error(
				'client_access_portal_google_drive_direct_upload_invalid_file',
				__( 'Cannot start a Google Drive upload session without a valid file name and size.', 'client-access-portal-google-drive' )
			);
		}

		$metadata = wp_json_encode(
			array(
				'name'        => $file_name,
				'parents'     => array( $parent_id ),
				'description' => $note,
			)
		);

		$session_response = wp_remote_request(
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true&fields=id,name,mimeType,size,modifiedTime,webViewLink,iconLink,thumbnailLink,parents,description',
			array(
				'method'  => 'POST',
				'timeout' => 20,
				'headers' => array(
					'Authorization'           => 'Bearer ' . $token,
					'Content-Type'            => 'application/json; charset=UTF-8',
					'X-Upload-Content-Type'   => $file_type,
					'X-Upload-Content-Length' => (string) $file_size,
				),
				'body'    => $metadata,
			)
		);

		if ( is_wp_error( $session_response ) ) {
			return $session_response;
		}

		$session_status = wp_remote_retrieve_response_code( $session_response );
		$session_url    = wp_remote_retrieve_header( $session_response, 'location' );

		if ( $session_status < 200 || $session_status >= 300 || '' === $session_url ) {
			return $this->google_upload_error( $session_response, __( 'Google Drive could not start a resumable upload session.', 'client-access-portal-google-drive' ) );
		}

		return array(
			'upload_url' => $session_url,
		);
	}

	private function upload_file_resumable( string $parent_id, array $upload, string $note, int $file_size ) {
		$token = $this->auth->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$mime_type = ! empty( $upload['type'] ) ? $upload['type'] : 'application/octet-stream';
		$metadata  = wp_json_encode(
			array(
				'name'        => sanitize_file_name( $upload['name'] ),
				'parents'     => array( $parent_id ),
				'description' => $note,
			)
		);

		$session_response = wp_remote_request(
			'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true&fields=id,name,mimeType,size,modifiedTime,webViewLink,iconLink,thumbnailLink,parents,description',
			array(
				'method'  => 'POST',
				'timeout' => 20,
				'headers' => array(
					'Authorization'              => 'Bearer ' . $token,
					'Content-Type'               => 'application/json; charset=UTF-8',
					'X-Upload-Content-Type'      => $mime_type,
					'X-Upload-Content-Length'    => (string) $file_size,
				),
				'body'    => $metadata,
			)
		);

		if ( is_wp_error( $session_response ) ) {
			return $session_response;
		}

		$session_status = wp_remote_retrieve_response_code( $session_response );
		$session_url    = wp_remote_retrieve_header( $session_response, 'location' );

		if ( $session_status < 200 || $session_status >= 300 || '' === $session_url ) {
			return $this->google_upload_error( $session_response, __( 'Google Drive could not start a resumable upload session.', 'client-access-portal-google-drive' ) );
		}

		$handle = fopen( $upload['tmp_name'], 'rb' );

		if ( false === $handle ) {
			return new \WP_Error(
				'client_access_portal_google_drive_upload_read_failed',
				__( 'The uploaded file could not be read before transfer to Google Drive.', 'client-access-portal-google-drive' )
			);
		}

		$chunk_size = 8 * MB_IN_BYTES;
		$offset     = 0;
		$final_body = array();

		while ( ! feof( $handle ) && $offset < $file_size ) {
			$chunk = fread( $handle, min( $chunk_size, $file_size - $offset ) );

			if ( false === $chunk ) {
				fclose( $handle );
				return new \WP_Error(
					'client_access_portal_google_drive_upload_read_failed',
					__( 'The uploaded file could not be read before transfer to Google Drive.', 'client-access-portal-google-drive' )
				);
			}

			$chunk_length = strlen( $chunk );

			if ( 0 === $chunk_length ) {
				fclose( $handle );
				return new \WP_Error(
					'client_access_portal_google_drive_upload_read_failed',
					__( 'The uploaded file could not be read before transfer to Google Drive.', 'client-access-portal-google-drive' )
				);
			}

			$range_end    = $offset + $chunk_length - 1;
			$response     = wp_remote_request(
				$session_url,
				array(
					'method'      => 'PUT',
					'timeout'     => 60,
					'redirection' => 0,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => $mime_type,
						'Content-Length' => (string) $chunk_length,
						'Content-Range' => sprintf( 'bytes %d-%d/%d', $offset, $range_end, $file_size ),
					),
					'body'    => $chunk,
				)
			);

			if ( is_wp_error( $response ) ) {
				fclose( $handle );
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			if ( 308 === $status_code ) {
				$offset += $chunk_length;
				continue;
			}

			if ( $status_code < 200 || $status_code >= 300 ) {
				fclose( $handle );
				return $this->google_upload_error( $response, __( 'Google Drive rejected an upload chunk.', 'client-access-portal-google-drive' ) );
			}

			$final_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$offset    += $chunk_length;
		}

		fclose( $handle );

		if ( ! is_array( $final_body ) || empty( $final_body['id'] ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_upload_incomplete',
				__( 'Google Drive did not return a completed upload record.', 'client-access-portal-google-drive' )
			);
		}

		return $final_body;
	}

	private function google_upload_error( $response, string $fallback_message ): \WP_Error {
		$status_code   = wp_remote_retrieve_response_code( $response );
		$body          = json_decode( wp_remote_retrieve_body( $response ), true );
		$error_message = is_array( $body ) && ! empty( $body['error']['message'] ) ? $body['error']['message'] : $fallback_message;

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

	public function update_file_description( string $file_id, string $description ) {
		$url = add_query_arg(
			array(
				'supportsAllDrives' => 'true',
				'fields'            => 'id,name,mimeType,size,modifiedTime,webViewLink,iconLink,thumbnailLink,parents,description',
			),
			'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $file_id )
		);

		return $this->request(
			'PATCH',
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json; charset=UTF-8',
				),
				'body'    => wp_json_encode(
					array(
						'description' => $description,
					)
				),
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
