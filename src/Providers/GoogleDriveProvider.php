<?php

namespace ClientAccessPortalGoogleDrive\Providers;

use ClientAccessPortal\Contracts\StorageProvider;
use ClientAccessPortalGoogleDrive\Service\DriveService;
use ClientAccessPortalGoogleDrive\Support\Settings;

class GoogleDriveProvider implements StorageProvider {
	private DriveService $drive_service;

	private Settings $settings;

	public function __construct( Settings $settings, DriveService $drive_service ) {
		$this->settings      = $settings;
		$this->drive_service = $drive_service;
	}

	public function key(): string {
		return 'google-drive';
	}

	public function label(): string {
		return 'Google Drive';
	}

	public function capabilities(): array {
		return array(
			'provision_client_container' => true,
			'provision_review_container' => true,
			'metadata_sync'              => true,
			'stream_file'                => true,
			'upload_for_review'          => true,
			'external_view_link'         => true,
		);
	}

	public function create_client_container( array $client_data ): mixed {
		$validation = $this->validate_configuration();

		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$settings    = $this->settings->all();
		$folder_name = sanitize_title( $client_data['client']['client_name'] ) . '-' . (int) $client_data['client']['id'];

		return $this->drive_service->create_folder( $folder_name, $settings['master_folder_id'] );
	}

	public function create_review_container( array $client_data ): mixed {
		$root_folder_id = $client_data['root_folder_id'] ?? '';
		$settings       = $this->settings->all();

		if ( empty( $root_folder_id ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_root_folder',
				__( 'Cannot create the review folder before the client root folder exists.', 'client-access-portal-google-drive' )
			);
		}

		return $this->drive_service->create_folder( $settings['review_folder_name'], $root_folder_id );
	}

	public function list_visible_items( array $client_data ): mixed {
		$link_map        = $client_data['provider_links'] ?? array();
		$root_folder_id  = $link_map['root_folder']['external_id'] ?? '';
		$review_folder_id = $link_map['review_folder']['external_id'] ?? '';

		if ( empty( $root_folder_id ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_root_link',
				__( 'This client does not have a Google Drive root folder linked yet.', 'client-access-portal-google-drive' )
			);
		}

		$items = $this->drive_service->list_children( $root_folder_id );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$visible_items = array();

		foreach ( $items as $item ) {
			if ( $review_folder_id && $review_folder_id === ( $item['id'] ?? '' ) ) {
				continue;
			}

			$visible_items[] = $this->map_item( $item );
		}

		return $visible_items;
	}

	public function list_review_items( array $client_data ): mixed {
		$link_map         = $client_data['provider_links'] ?? array();
		$review_folder_id = $link_map['review_folder']['external_id'] ?? '';

		if ( empty( $review_folder_id ) ) {
			return array();
		}

		$items = $this->drive_service->list_children( $review_folder_id );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		return array_map( array( $this, 'map_item' ), $items );
	}

	public function stream_file( array $file_record ): mixed {
		$file_id = $file_record['id'] ?? '';

		if ( empty( $file_id ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_file_id',
				__( 'Cannot stream a Google Drive file without a file ID.', 'client-access-portal-google-drive' )
			);
		}

		$file = $this->drive_service->get_file( $file_id );

		if ( is_wp_error( $file ) ) {
			return $file;
		}

		if ( $this->is_google_workspace_file( $file['mimeType'] ?? '' ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_workspace_file',
				__( 'Google Workspace files should be opened through their web view link.', 'client-access-portal-google-drive' )
			);
		}

		$download = $this->drive_service->download_file( $file_id );

		if ( is_wp_error( $download ) ) {
			return $download;
		}

		return array(
			'file'      => $this->map_item( $file ),
			'download'  => $download,
		);
	}

	public function upload_for_review( array $client_data, array $upload ): mixed {
		$link_map         = $client_data['provider_links'] ?? array();
		$review_folder_id = $link_map['review_folder']['external_id'] ?? '';

		if ( empty( $review_folder_id ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_review_link',
				__( 'This client does not have a Google Drive review folder linked yet.', 'client-access-portal-google-drive' )
			);
		}

		$item = $this->drive_service->upload_file( $review_folder_id, $upload );

		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return $this->map_item( $item );
	}

	public function get_external_view_link( array $file_record ): ?string {
		if ( ! empty( $file_record['web_view_link'] ) ) {
			return (string) $file_record['web_view_link'];
		}

		if ( empty( $file_record['id'] ) ) {
			return null;
		}

		$file = $this->drive_service->get_file( (string) $file_record['id'] );

		if ( is_wp_error( $file ) ) {
			return null;
		}

		return isset( $file['webViewLink'] ) ? (string) $file['webViewLink'] : null;
	}

	public function reconcile_submission_state( array $submission_record ): mixed {
		return $this->not_implemented( __FUNCTION__ );
	}

	public function validate_configuration(): mixed {
		return $this->settings->validate();
	}

	public function health_status(): array {
		$valid = $this->validate_configuration();

		if ( is_wp_error( $valid ) ) {
			return array(
				'status'  => 'warning',
				'message' => $valid->get_error_message(),
			);
		}

		return array(
			'status'  => 'ok',
			'message' => __( 'Google Drive provider is configured and ready for client provisioning, listing, uploads, and downloads.', 'client-access-portal-google-drive' ),
		);
	}

	private function map_item( array $item ): array {
		$mime_type = $item['mimeType'] ?? '';

		return array(
			'id'             => $item['id'] ?? '',
			'name'           => $item['name'] ?? '',
			'mime_type'      => $mime_type,
			'size'           => isset( $item['size'] ) ? (int) $item['size'] : 0,
			'modified_time'  => $item['modifiedTime'] ?? '',
			'web_view_link'  => $item['webViewLink'] ?? '',
			'is_folder'      => 'application/vnd.google-apps.folder' === $mime_type,
			'is_workspace'   => $this->is_google_workspace_file( $mime_type ),
		);
	}

	private function is_google_workspace_file( string $mime_type ): bool {
		return 0 === strpos( $mime_type, 'application/vnd.google-apps.' ) && 'application/vnd.google-apps.folder' !== $mime_type;
	}

	private function not_implemented( string $method ): \WP_Error {
		return new \WP_Error(
			'client_access_portal_google_drive_not_implemented',
			sprintf(
				/* translators: %s: method name */
				__( 'Google Drive provider method %s is scaffolded but not implemented yet.', 'client-access-portal-google-drive' ),
				$method
			)
		);
	}
}
