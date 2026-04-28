<?php

namespace ClientAccessPortalGoogleDrive\Service;

use ClientAccessPortalGoogleDrive\Providers\GoogleDriveProvider;
use ClientAccessPortalGoogleDrive\Support\CoreBridge;
use ClientAccessPortalGoogleDrive\Support\Settings;

class FileSyncService {
	private const BASELINE_OPTION = 'client_access_portal_google_drive_notification_baselines';

	private CoreBridge $core_bridge;

	private Settings $settings;

	private DriveService $drive_service;

	public function __construct( CoreBridge $core_bridge, Settings $settings, DriveService $drive_service ) {
		$this->core_bridge   = $core_bridge;
		$this->settings      = $settings;
		$this->drive_service = $drive_service;
	}

	public function sync_visible_files(): void {
		if ( ! $this->core_bridge->is_available() ) {
			return;
		}

		$provider = new GoogleDriveProvider( $this->settings, $this->drive_service );
		$clients  = client_access_portal()->client_repository()->get_active_by_provider( 'google-drive' );

		foreach ( $clients as $client ) {
			$client_id      = (int) ( $client['id'] ?? 0 );
			$provider_links = client_access_portal()->provider_link_repository()->map_by_resource_type( $client_id, 'google-drive' );
			$visible_items  = $provider->list_visible_items(
				array(
					'client'         => $client,
					'provider'       => $provider,
					'provider_links' => $provider_links,
				)
			);

			if ( is_wp_error( $visible_items ) || empty( $client_id ) ) {
				continue;
			}

			$visible_files = array_values(
				array_filter(
					$visible_items,
					static fn( array $item ): bool => empty( $item['is_folder'] )
				)
			);

			if ( ! $this->has_baseline( $client_id ) ) {
				foreach ( $visible_files as $item ) {
					$this->upsert_visible_file( $client_id, $item, 'provider', 'approved' );
				}

				$this->mark_baseline( $client_id );
				continue;
			}

			$new_files = array();

			foreach ( $visible_files as $item ) {
				$file_id = trim( (string) ( $item['id'] ?? '' ) );

				if ( '' === $file_id ) {
					continue;
				}

				$existing = client_access_portal()->file_repository()->get_by_client_provider_external_id( $client_id, 'google-drive', $file_id );

				if ( $existing ) {
					$source = 'portal' === (string) ( $existing['source'] ?? '' ) ? 'portal' : 'provider';
					$this->upsert_visible_file( $client_id, $item, $source, 'approved' );
					continue;
				}

				$this->upsert_visible_file( $client_id, $item, 'provider', 'approved' );
				$new_files[] = $item;
			}

			if ( ! empty( $new_files ) ) {
				do_action( 'client_access_portal_after_staff_files_detected', $client_id, $new_files, $client );
			}
		}
	}

	private function upsert_visible_file( int $client_id, array $item, string $source, string $status ): void {
		$file_name = sanitize_file_name( (string) ( $item['name'] ?? '' ) );

		client_access_portal()->file_repository()->upsert(
			array(
				'client_id'          => $client_id,
				'provider_slug'      => 'google-drive',
				'external_object_id' => (string) ( $item['id'] ?? '' ),
				'path'               => '/' . $file_name,
				'parent_path'        => '/',
				'file_name'          => $file_name,
				'mime_type'          => (string) ( $item['mime_type'] ?? '' ),
				'file_size'          => (int) ( $item['size'] ?? 0 ),
				'is_folder'          => ! empty( $item['is_folder'] ),
				'source'             => $source,
				'status'             => $status,
				'external_view_url'  => (string) ( $item['web_view_link'] ?? '' ),
				'modified_at'        => (string) ( $item['modified_time'] ?? '' ),
				'synced_at'          => current_time( 'mysql', true ),
			)
		);
	}

	private function has_baseline( int $client_id ): bool {
		$baselines = get_option( self::BASELINE_OPTION, array() );

		return is_array( $baselines ) && ! empty( $baselines[ $client_id ] );
	}

	private function mark_baseline( int $client_id ): void {
		$baselines = get_option( self::BASELINE_OPTION, array() );

		if ( ! is_array( $baselines ) ) {
			$baselines = array();
		}

		$baselines[ $client_id ] = current_time( 'mysql', true );
		update_option( self::BASELINE_OPTION, $baselines, false );
	}
}
