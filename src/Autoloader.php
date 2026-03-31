<?php

namespace ClientAccessPortalGoogleDrive;

class Autoloader {
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	public static function autoload( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$path           = CLIENT_ACCESS_PORTAL_GOOGLE_DRIVE_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}
