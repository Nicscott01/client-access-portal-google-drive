<?php

namespace ClientAccessPortalGoogleDrive;

use ClientAccessPortalGoogleDrive\Admin\NoticeService;
use ClientAccessPortal\Providers\ProviderRegistry;
use ClientAccessPortalGoogleDrive\Admin\ClientDriveTools;
use ClientAccessPortalGoogleDrive\Admin\SettingsSection;
use ClientAccessPortalGoogleDrive\Admin\TestConnectionAction;
use ClientAccessPortalGoogleDrive\Providers\GoogleDriveProvider;
use ClientAccessPortalGoogleDrive\Service\ConnectionTester;
use ClientAccessPortalGoogleDrive\Service\DriveService;
use ClientAccessPortalGoogleDrive\Service\ServiceAccountAuth;
use ClientAccessPortalGoogleDrive\Support\CoreBridge;
use ClientAccessPortalGoogleDrive\Support\Settings;

class Plugin {
	private CoreBridge $core_bridge;

	private Settings $settings;

	private NoticeService $notice_service;

	private SettingsSection $settings_section;

	private TestConnectionAction $test_connection_action;

	private DriveService $drive_service;

	private ServiceAccountAuth $service_account_auth;

	private ClientDriveTools $client_drive_tools;

	public function __construct() {
		$this->core_bridge      = new CoreBridge();
		$this->settings         = new Settings();
		$this->notice_service   = new NoticeService();
		$this->service_account_auth = new ServiceAccountAuth( $this->settings );
		$this->drive_service    = new DriveService( $this->settings, $this->service_account_auth );
		$this->settings_section = new SettingsSection( $this->core_bridge, $this->settings );
		$this->test_connection_action = new TestConnectionAction(
			$this->core_bridge,
			new ConnectionTester( $this->settings, $this->service_account_auth ),
			$this->notice_service
		);
		$this->client_drive_tools = new ClientDriveTools(
			$this->core_bridge,
			$this->settings,
			$this->drive_service,
			$this->notice_service
		);
	}

	public function boot(): void {
		add_action( 'client_access_portal_register_providers', array( $this, 'register_provider' ) );
		add_action( 'client_access_portal_admin_settings_sections', array( $this->settings_section, 'render' ) );
		add_action( 'admin_init', array( $this->settings_section, 'register' ) );
		add_action( 'admin_init', array( $this->test_connection_action, 'register' ) );
		add_action( 'admin_init', array( $this->client_drive_tools, 'register' ) );
		add_action( 'admin_notices', array( $this->notice_service, 'render' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_show_dependency_notice' ), 5 );
		add_action( 'client_access_portal_after_client_created', array( $this, 'maybe_provision_client' ), 10, 3 );
		add_action( 'client_access_portal_after_client_updated', array( $this, 'maybe_provision_client_after_update' ), 10, 3 );
	}

	public function register_provider( ProviderRegistry $registry ): void {
		if ( ! $this->core_bridge->is_available() ) {
			return;
		}

		$registry->register( new GoogleDriveProvider( $this->settings, $this->drive_service ) );
	}

	public function maybe_show_dependency_notice(): void {
		if ( $this->core_bridge->is_available() ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'render_dependency_notice' ) );
	}

	public function render_dependency_notice(): void {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Client Access Portal - Google Drive requires the Client Access Portal core plugin to be active. The addon now detects the active core by runtime constants, so either a release folder or a `-dev` folder will work as long as only one core build is active.', 'client-access-portal-google-drive' ); ?></p>
		</div>
		<?php
	}

	public function maybe_provision_client( int $client_id, string $provider_key, array $payload ): void {
		if ( 'google-drive' !== $provider_key || ! $this->core_bridge->is_available() ) {
			return;
		}

		$this->provision_client_if_needed( $client_id );
	}

	public function maybe_provision_client_after_update( int $client_id, ?array $previous_client, array $updated_data ): void {
		$new_provider = sanitize_key( $updated_data['primary_provider'] ?? '' );

		if ( 'google-drive' !== $new_provider || ! $this->core_bridge->is_available() ) {
			return;
		}

		$this->provision_client_if_needed( $client_id );
	}

	private function provision_client_if_needed( int $client_id ): void {
		$existing_links = client_access_portal()->provider_link_repository()->map_by_resource_type( $client_id, 'google-drive' );

		if ( ! empty( $existing_links['root_folder'] ) && ! empty( $existing_links['review_folder'] ) ) {
			return;
		}

		$provider = new GoogleDriveProvider( $this->settings, $this->drive_service );
		$client   = client_access_portal()->client_repository()->get( $client_id );

		if ( ! $client ) {
			$this->notice_service->error( __( 'Google Drive provisioning could not start because the client record was not found.', 'client-access-portal-google-drive' ) );
			return;
		}

		$root_folder = $provider->create_client_container(
			array(
				'client' => $client,
			)
		);

		if ( is_wp_error( $root_folder ) ) {
			$this->notice_service->error( $root_folder->get_error_message() );
			return;
		}

		$review_folder = $provider->create_review_container(
			array(
				'client'         => $client,
				'root_folder_id' => $root_folder['id'] ?? '',
			)
		);

		if ( is_wp_error( $review_folder ) ) {
			$this->notice_service->error( $review_folder->get_error_message() );
			return;
		}

		client_access_portal()->provider_link_repository()->create(
			array(
				'client_id'     => $client_id,
				'provider_slug' => 'google-drive',
				'resource_type' => 'root_folder',
				'external_id'   => $root_folder['id'],
				'metadata'      => $root_folder,
			)
		);

		client_access_portal()->provider_link_repository()->create(
			array(
				'client_id'     => $client_id,
				'provider_slug' => 'google-drive',
				'resource_type' => 'review_folder',
				'external_id'   => $review_folder['id'],
				'metadata'      => $review_folder,
			)
		);

		$this->notice_service->success(
			sprintf(
				/* translators: %s: client name */
				__( 'Google Drive folders were provisioned for %s.', 'client-access-portal-google-drive' ),
				$client['client_name']
			)
		);
	}
}
