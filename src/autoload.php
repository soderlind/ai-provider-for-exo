<?php
/**
 * Autoloader for the Aiprfoex namespace.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( static function ( string $class ): void {
	$prefix = 'Aiprfoex\\';

	if ( ! str_starts_with( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( file_exists( $file ) ) {
		require $file;
	}
} );
