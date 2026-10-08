<?php
/**
 * Uninstall Elementor to Bricks Media Migrator.
 *
 * @package Elementor_To_Bricks_Media_Migrator
 * @author  Abid KP (https://abidkp.com)
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete user meta keys (both current and legacy)
$meta_keys = array( '_ebmm_imported_export_v1', '_gmm_imported_export_v1' );
foreach ( $meta_keys as $meta_key ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
			$meta_key
		)
	);
}

// Delete uploaded import directories
$upload_dir = wp_upload_dir();
$dirs_to_clean = array(
	trailingslashit( $upload_dir['basedir'] ) . 'ebmm-imports',
	trailingslashit( $upload_dir['basedir'] ) . 'gmm-imports',
);

foreach ( $dirs_to_clean as $dir ) {
	if ( is_dir( $dir ) ) {
		$files = glob( $dir . '/*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file );
				}
			}
		}
		@rmdir( $dir );
	}
}
