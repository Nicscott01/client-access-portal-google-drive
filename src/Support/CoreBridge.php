<?php

namespace ClientAccessPortalGoogleDrive\Support;

class CoreBridge {
	public function is_available(): bool {
		return defined( 'CLIENT_ACCESS_PORTAL_BOOTSTRAPPED' )
			&& true === CLIENT_ACCESS_PORTAL_BOOTSTRAPPED
			&& function_exists( 'client_access_portal' )
			&& class_exists( '\ClientAccessPortal\Plugin' );
	}

	public function plugin_slug(): string {
		return defined( 'CLIENT_ACCESS_PORTAL_PLUGIN_SLUG' ) ? (string) CLIENT_ACCESS_PORTAL_PLUGIN_SLUG : '';
	}

	public function version(): string {
		return defined( 'CLIENT_ACCESS_PORTAL_VERSION' ) ? (string) CLIENT_ACCESS_PORTAL_VERSION : '';
	}

	public function api_version(): string {
		return defined( 'CLIENT_ACCESS_PORTAL_API_VERSION' ) ? (string) CLIENT_ACCESS_PORTAL_API_VERSION : '';
	}

	public function is_dev_install(): bool {
		return defined( 'CLIENT_ACCESS_PORTAL_IS_DEV' ) && true === CLIENT_ACCESS_PORTAL_IS_DEV;
	}
}
