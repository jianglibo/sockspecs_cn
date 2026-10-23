<?php
/** Domestic mail remains blocked unless its private Cloudflare configuration enables it. */
if ( defined( 'ABSPATH' ) && defined( 'SSCN_DOMESTIC_SITE' ) && SSCN_DOMESTIC_SITE ) {
	require_once __DIR__ . '/cloudflare-email.php';
}
