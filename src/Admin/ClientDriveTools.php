<?php

namespace ClientAccessPortalGoogleDrive\Admin;

use ClientAccessPortalGoogleDrive\Service\DriveService;
use ClientAccessPortalGoogleDrive\Support\CoreBridge;
use ClientAccessPortalGoogleDrive\Support\Settings;

class ClientDriveTools {
	private CoreBridge $core_bridge;

	private Settings $settings;

	private DriveService $drive_service;

	private NoticeService $notice_service;

	public function __construct( CoreBridge $core_bridge, Settings $settings, DriveService $drive_service, NoticeService $notice_service ) {
		$this->core_bridge    = $core_bridge;
		$this->settings       = $settings;
		$this->drive_service  = $drive_service;
		$this->notice_service = $notice_service;
	}

	public function register(): void {
		add_action( 'client_access_portal_admin_client_sections', array( $this, 'render' ) );
		add_action( 'admin_post_client_access_portal_google_drive_reprovision', array( $this, 'handle_reprovision' ) );
		add_action( 'admin_post_client_access_portal_google_drive_relink', array( $this, 'handle_relink' ) );
	}

	public function render( array $client ): void {
		if ( ! $this->core_bridge->is_available() || 'google-drive' !== ( $client['primary_provider'] ?? '' ) ) {
			return;
		}

		$link_map          = client_access_portal()->provider_link_repository()->map_by_resource_type( (int) $client['id'], 'google-drive' );
		$root_folder_id    = $link_map['root_folder']['external_id'] ?? '';
		$review_folder_id  = $link_map['review_folder']['external_id'] ?? '';
		$settings_health   = $this->settings->health_summary();
		?>
		<h2><?php esc_html_e( 'Google Drive Tools', 'client-access-portal-google-drive' ); ?></h2>
		<p><?php esc_html_e( 'Use these tools to relink an existing client to Shared Drive folders or provision a fresh folder pair under the currently configured master folder.', 'client-access-portal-google-drive' ); ?></p>
		<p><strong><?php esc_html_e( 'Configuration health:', 'client-access-portal-google-drive' ); ?></strong> <?php echo esc_html( $settings_health['message'] ); ?></p>

		<h3><?php esc_html_e( 'Current Link State', 'client-access-portal-google-drive' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Root folder ID', 'client-access-portal-google-drive' ); ?></th>
				<td><code><?php echo esc_html( $root_folder_id ? $root_folder_id : __( 'Not linked', 'client-access-portal-google-drive' ) ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Review folder ID', 'client-access-portal-google-drive' ); ?></th>
				<td><code><?php echo esc_html( $review_folder_id ? $review_folder_id : __( 'Not linked', 'client-access-portal-google-drive' ) ); ?></code></td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Save Existing Folder IDs', 'client-access-portal-google-drive' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Use this when you already created the client folder structure in Google Drive and just need this plugin to point at it.', 'client-access-portal-google-drive' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'client_access_portal_google_drive_relink' ); ?>
			<input type="hidden" name="action" value="client_access_portal_google_drive_relink">
			<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client['id'] ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="client-access-portal-google-drive-root"><?php esc_html_e( 'Root folder ID', 'client-access-portal-google-drive' ); ?></label></th>
					<td><input id="client-access-portal-google-drive-root" name="root_folder_id" type="text" class="regular-text" value="<?php echo esc_attr( $root_folder_id ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="client-access-portal-google-drive-review"><?php esc_html_e( 'Review folder ID', 'client-access-portal-google-drive' ); ?></label></th>
					<td>
						<input id="client-access-portal-google-drive-review" name="review_folder_id" type="text" class="regular-text" value="<?php echo esc_attr( $review_folder_id ); ?>">
						<p class="description"><?php esc_html_e( 'Optional. Leave blank to create a new review folder under the supplied root folder.', 'client-access-portal-google-drive' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Folder IDs', 'client-access-portal-google-drive' ), 'secondary' ); ?>
		</form>

		<h3><?php esc_html_e( 'Provision New Folders', 'client-access-portal-google-drive' ); ?></h3>
		<div class="notice notice-warning inline">
			<p><strong><?php esc_html_e( 'Caution:', 'client-access-portal-google-drive' ); ?></strong> <?php esc_html_e( 'This creates a fresh root folder and review folder under the current master folder and rewires this client to those new IDs. Existing old folders are not deleted or migrated.', 'client-access-portal-google-drive' ); ?></p>
			<p><?php esc_html_e( 'Only use this when you intentionally want to abandon the current folder links for this client.', 'client-access-portal-google-drive' ); ?></p>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'client_access_portal_google_drive_reprovision' ); ?>
			<input type="hidden" name="action" value="client_access_portal_google_drive_reprovision">
			<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client['id'] ); ?>">
			<p>
				<label for="client-access-portal-google-drive-confirm-reprovision">
					<input id="client-access-portal-google-drive-confirm-reprovision" name="confirm_reprovision" type="checkbox" value="1" required>
					<?php esc_html_e( 'I understand this will create a new Google Drive folder pair and replace the saved folder links for this client.', 'client-access-portal-google-drive' ); ?>
				</label>
			</p>
			<?php submit_button( __( 'Provision New Google Drive Folders', 'client-access-portal-google-drive' ), 'delete', 'submit', false ); ?>
		</form>
		<?php
	}

	public function handle_reprovision(): void {
		if ( ! current_user_can( 'client_access_portal_manage_clients' ) ) {
			wp_die( esc_html__( 'You are not allowed to reprovision Google Drive folders.', 'client-access-portal-google-drive' ) );
		}

		check_admin_referer( 'client_access_portal_google_drive_reprovision' );

		$client_id = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
		$client    = client_access_portal()->client_repository()->get( $client_id );
		$confirmed = ! empty( $_POST['confirm_reprovision'] );

		if ( ! $confirmed ) {
			$this->notice_service->error( __( 'Please confirm that you want to provision new Google Drive folders for this client.', 'client-access-portal-google-drive' ) );
			$this->redirect_to_client( $client_id );
		}

		if ( ! $client || 'google-drive' !== ( $client['primary_provider'] ?? '' ) ) {
			$this->notice_service->error( __( 'This client is not assigned to Google Drive.', 'client-access-portal-google-drive' ) );
			$this->redirect_to_client( $client_id );
		}

		$validation = $this->settings->validate();

		if ( is_wp_error( $validation ) ) {
			$this->notice_service->error( $validation->get_error_message() );
			$this->redirect_to_client( $client_id );
		}

		$folder_name = sanitize_title( $client['client_name'] ) . '-' . (int) $client['id'];
		$settings    = $this->settings->all();
		$root_folder = $this->drive_service->create_folder( $folder_name, $settings['master_folder_id'] );

		if ( is_wp_error( $root_folder ) ) {
			$this->notice_service->error( $root_folder->get_error_message() );
			$this->redirect_to_client( $client_id );
		}

		$review_folder = $this->drive_service->create_folder( $settings['review_folder_name'], $root_folder['id'] );

		if ( is_wp_error( $review_folder ) ) {
			$this->notice_service->error( $review_folder->get_error_message() );
			$this->redirect_to_client( $client_id );
		}

		$this->save_folder_links( $client_id, $root_folder, $review_folder );
		$this->notice_service->success( __( 'New Google Drive folders were provisioned and linked to this client.', 'client-access-portal-google-drive' ) );
		$this->redirect_to_client( $client_id );
	}

	public function handle_relink(): void {
		if ( ! current_user_can( 'client_access_portal_manage_clients' ) ) {
			wp_die( esc_html__( 'You are not allowed to relink Google Drive folders.', 'client-access-portal-google-drive' ) );
		}

		check_admin_referer( 'client_access_portal_google_drive_relink' );

		$client_id        = isset( $_POST['client_id'] ) ? absint( wp_unslash( $_POST['client_id'] ) ) : 0;
		$root_folder_id   = isset( $_POST['root_folder_id'] ) ? sanitize_text_field( wp_unslash( $_POST['root_folder_id'] ) ) : '';
		$review_folder_id = isset( $_POST['review_folder_id'] ) ? sanitize_text_field( wp_unslash( $_POST['review_folder_id'] ) ) : '';
		$client           = client_access_portal()->client_repository()->get( $client_id );

		if ( ! $client || 'google-drive' !== ( $client['primary_provider'] ?? '' ) ) {
			$this->notice_service->error( __( 'This client is not assigned to Google Drive.', 'client-access-portal-google-drive' ) );
			$this->redirect_to_client( $client_id );
		}

		$root_folder = $this->validate_folder_id( $root_folder_id );

		if ( is_wp_error( $root_folder ) ) {
			$this->notice_service->error( $root_folder->get_error_message() );
			$this->redirect_to_client( $client_id );
		}

		if ( $review_folder_id ) {
			$review_folder = $this->validate_folder_id( $review_folder_id );
		} else {
			$settings      = $this->settings->all();
			$review_folder = $this->drive_service->create_folder( $settings['review_folder_name'], $root_folder['id'] );
		}

		if ( is_wp_error( $review_folder ) ) {
			$this->notice_service->error( $review_folder->get_error_message() );
			$this->redirect_to_client( $client_id );
		}

		$this->save_folder_links( $client_id, $root_folder, $review_folder );
		$this->notice_service->success( __( 'Google Drive folder IDs were saved for this client.', 'client-access-portal-google-drive' ) );
		$this->redirect_to_client( $client_id );
	}

	private function validate_folder_id( string $folder_id ) {
		if ( '' === $folder_id ) {
			return new \WP_Error(
				'client_access_portal_google_drive_missing_folder_id',
				__( 'A Google Drive folder ID is required.', 'client-access-portal-google-drive' )
			);
		}

		$folder = $this->drive_service->get_file( $folder_id );

		if ( is_wp_error( $folder ) ) {
			return $folder;
		}

		if ( 'application/vnd.google-apps.folder' !== ( $folder['mimeType'] ?? '' ) ) {
			return new \WP_Error(
				'client_access_portal_google_drive_not_folder',
				__( 'The provided Google Drive ID is valid, but it does not point to a folder.', 'client-access-portal-google-drive' )
			);
		}

		return $folder;
	}

	private function save_folder_links( int $client_id, array $root_folder, array $review_folder ): void {
		client_access_portal()->provider_link_repository()->upsert(
			array(
				'client_id'     => $client_id,
				'provider_slug' => 'google-drive',
				'resource_type' => 'root_folder',
				'external_id'   => $root_folder['id'],
				'metadata'      => $root_folder,
			)
		);

		client_access_portal()->provider_link_repository()->upsert(
			array(
				'client_id'     => $client_id,
				'provider_slug' => 'google-drive',
				'resource_type' => 'review_folder',
				'external_id'   => $review_folder['id'],
				'metadata'      => $review_folder,
			)
		);
	}

	private function redirect_to_client( int $client_id ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=client-access-portal-edit&client_id=' . $client_id ) );
		exit;
	}
}
