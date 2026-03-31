<?php

namespace ClientAccessPortalGoogleDrive\Support;

class Settings {
	public function defaults(): array {
		return array(
			'credentials_path'        => '',
			'master_folder_id'        => '',
			'review_folder_name'      => 'Uploads for Review',
			'sync_interval_minutes'   => 5,
			'alert_email'             => get_option( 'admin_email' ),
			'notify_on_client_upload' => 1,
			'notification_recipient'  => get_option( 'admin_email' ),
		);
	}

	public function all(): array {
		$settings = wp_parse_args( get_option( 'client_access_portal_google_drive_settings', array() ), $this->defaults() );

		if ( defined( 'CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_CREDENTIALS_PATH' ) && CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_CREDENTIALS_PATH ) {
			$settings['credentials_path'] = (string) CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_CREDENTIALS_PATH;
		}

		return $settings;
	}

	public function validate() {
		$settings = $this->all();

		if ( empty( $settings['credentials_path'] ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_credentials_path',
				__( 'Set a Google service account credentials path before enabling Drive operations.', 'client-access-portal-google-drive' )
			);
		}

		if ( empty( $settings['master_folder_id'] ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_master_folder',
				__( 'Set a master Google Drive folder ID before enabling Drive operations.', 'client-access-portal-google-drive' )
			);
		}

		return true;
	}

	public function health_summary(): array {
		$validation = $this->validate();

		if ( is_wp_error( $validation ) ) {
			return array(
				'status'  => 'warning',
				'message' => $validation->get_error_message(),
			);
		}

		return array(
			'status'  => 'ok',
			'message' => __( 'Google Drive settings are present. API wiring is still in progress.', 'client-access-portal-google-drive' ),
		);
	}
}
