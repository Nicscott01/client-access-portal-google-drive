<?php
/**
 * Plugin Name: Client Access Portal - Google Drive
 * Description: Google Drive storage provider addon for Client Access Portal.
 * Version: 0.1.1
 * Author: Nic Scott
 * Text Domain: client-access-portal-google-drive
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_VERSION', '0.1.1' );
define( 'CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_PLUGIN_FILE', __FILE__ );
define( 'CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_PLUGIN_DIR . 'src/Autoloader.php';

ClientAccessPortalGoogleDrive\Autoloader::register();

function client_access_portal_google_drive(): ClientAccessPortalGoogleDrive\Plugin {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new ClientAccessPortalGoogleDrive\Plugin();
	}

	return $plugin;
}

register_activation_hook( __FILE__, array( client_access_portal_google_drive(), 'activate' ) );
register_deactivation_hook( __FILE__, array( client_access_portal_google_drive(), 'deactivate' ) );

client_access_portal_google_drive()->boot();
