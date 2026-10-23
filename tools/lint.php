<?php
/** Syntax-check project PHP without scanning vendor or local WordPress. */
$root = dirname( __DIR__ );
$failed = false;
foreach ( array( 'parameter-commerce', 'sscn-commerce', 'tests', 'tools', 'theme' ) as $directory ) {
	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/$directory", FilesystemIterator::SKIP_DOTS ) );
	foreach ( $files as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		passthru( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file->getPathname() ), $status );
		$failed = $failed || 0 !== $status;
	}
}
exit( $failed ? 1 : 0 );
