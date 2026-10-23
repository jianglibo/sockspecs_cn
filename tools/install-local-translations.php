<?php
/** Install only translation files from the official archive, never PHP or paths. */
$root = dirname( __DIR__ );
$archive = new ZipArchive();
if ( true !== $archive->open( "$root/.local/bin/woocommerce-zh_CN.zip" ) ) {
	throw new RuntimeException( 'Unable to open official translation archive.' );
}
$destination = "$root/.local/wordpress/wp-content/languages/plugins";
if ( ! is_dir( $destination ) ) {
	mkdir( $destination, 0755, true );
}
$count = 0;
for ( $i = 0; $i < $archive->numFiles; ++$i ) {
	$name = $archive->getNameIndex( $i );
	if ( ! preg_match( '/^woocommerce-zh_CN(?:-[a-f0-9]+)?\.(?:mo|po|json)$/D', $name ) ) {
		continue;
	}
	if ( false === file_put_contents( "$destination/$name", $archive->getFromIndex( $i ) ) ) {
		throw new RuntimeException( 'Unable to write translation file.' );
	}
	++$count;
}
$archive->close();
if ( ! is_file( "$destination/woocommerce-zh_CN.mo" ) ) {
	throw new RuntimeException( 'WooCommerce Chinese translation missing.' );
}
echo "Installed $count official Chinese translation files.\n";
