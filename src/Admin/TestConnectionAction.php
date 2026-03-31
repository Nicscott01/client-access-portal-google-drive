<?php

namespace ClientAccessPortalGoogleDrive\Admin;

use ClientAccessPortalGoogleDrive\Service\ConnectionTester;
use ClientAccessPortalGoogleDrive\Support\CoreBridge;

class TestConnectionAction {
	private CoreBridge $core_bridge;

	private ConnectionTester $connection_tester;

	private NoticeService $notice_service;

	public function __construct( CoreBridge $core_bridge, ConnectionTester $connection_tester, NoticeService $notice_service ) {
		$this->core_bridge       = $core_bridge;
		$this->connection_tester = $connection_tester;
		$this->notice_service    = $notice_service;
	}

	public function register(): void {
		add_action( 'admin_post_client_access_portal_google_drive_test_connection', array( $this, 'handle' ) );
	}

	public function handle(): void {
		if ( ! current_user_can( 'client_access_portal_manage_client_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to test the Google Drive connection.', 'client-access-portal-google-drive' ) );
		}

		check_admin_referer( 'client_access_portal_google_drive_test_connection' );

		if ( ! $this->core_bridge->is_available() ) {
			$this->notice_service->error( __( 'Client Access Portal core is not active, so the Google Drive addon cannot run a connection test.', 'client-access-portal-google-drive' ) );
			$this->redirect();
		}

		$result = $this->connection_tester->test();

		if ( is_wp_error( $result ) ) {
			$this->notice_service->error( $result->get_error_message() );
			$this->redirect();
		}

		$this->notice_service->success(
			sprintf(
				/* translators: 1: folder name, 2: folder id */
				__( 'Google Drive connection succeeded. The service account can access folder "%1$s" (%2$s).', 'client-access-portal-google-drive' ),
				$result['folder_name'],
				$result['folder_id']
			)
		);

		$this->redirect();
	}

	private function redirect(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=client-access-portal-settings' ) );
		exit;
	}
}
