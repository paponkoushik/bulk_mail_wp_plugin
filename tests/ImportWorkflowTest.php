<?php

class ImportWorkflowTest extends WPBM_TestCase {

	public function testCleanupImportFileDeletesFilesInsideUploadsOnly(): void {
		$uploads        = wp_get_upload_dir();
		$uploads_target = trailingslashit( $uploads['basedir'] ) . $this->uniqueToken( 'import' ) . '.csv';
		$outside_target = dirname( __DIR__ ) . '/' . $this->uniqueToken( 'outside' ) . '.csv';

		file_put_contents( $uploads_target, "email\ninside@example.test\n" );
		file_put_contents( $outside_target, "email\noutside@example.test\n" );

		try {
			$this->invokePrivate( $this->plugin, 'cleanup_import_file', array( $uploads_target ) );
			$this->invokePrivate( $this->plugin, 'cleanup_import_file', array( $outside_target ) );

			$this->assertFalse( file_exists( $uploads_target ), 'Expected uploads import file to be deleted.' );
			$this->assertTrue( file_exists( $outside_target ), 'Expected non-uploads file to be kept.' );
		} finally {
			if ( file_exists( $uploads_target ) ) {
				@unlink( $uploads_target );
			}

			if ( file_exists( $outside_target ) ) {
				@unlink( $outside_target );
			}
		}
	}
}
