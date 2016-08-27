<?php
/**
 * Disable error reporting
 *
 * Set this to error_reporting( -1 ) for debugging.
 */
error_reporting(0);

/** Set ABSPATH for execution */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/' );
}

if ( !define( 'WPLS_PATH' ) ) {
	define( 'WPLS_PATH', dirname( __FILE__ ) . '/' );
}

define( 'WPINC', 'wp-includes' );

require( ABSPATH . 'wp-load.php' );
$s = isset( $_GET['s'] ) ? $_GET['s'] : 'worker';

if ( 'worker' === $s ) {
	$compress = ( isset($_GET['c']) && $_GET['c'] );
	$force_gzip = ( $compress && 'gzip' == $_GET['c'] );
	$expires_offset = 31536000; // 1 year

	if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && stripslashes( $_SERVER['HTTP_IF_NONE_MATCH'] ) === $wp_version ) {
		$protocol = $_SERVER['SERVER_PROTOCOL'];
		if ( ! in_array( $protocol, array( 'HTTP/1.1', 'HTTP/2', 'HTTP/2.0' ) ) ) {
			$protocol = 'HTTP/1.0';
		}
		header( "$protocol 304 Not Modified" );
		exit();
	}

	header("Etag: $wp_version");
	header('Content-Type: application/javascript; charset=UTF-8');
	header('Expires: ' . gmdate( "D, d M Y H:i:s", time() + $expires_offset ) . ' GMT');
	header("Cache-Control: public, max-age=$expires_offset");

	if ( $compress && ! ini_get('zlib.output_compression') && 'ob_gzhandler' != ini_get('output_handler') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) ) {
		header('Vary: Accept-Encoding'); // Handle proxies
		if ( false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') && function_exists('gzdeflate') && ! $force_gzip ) {
			header('Content-Encoding: deflate');
			$out = gzdeflate( $out, 3 );
		} elseif ( false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && function_exists('gzencode') ) {
			header('Content-Encoding: gzip');
			$out = gzencode( $out, 3 );
		}
	}

	// include( WPLS_PATH . 'wpls-push.php' );
	echo wpls_push()->buildWorker();
}

if ( 'manifest' == $s ) {
	@header( 'Content-Type: application/json; charset=' . get_bloginfo( 'blog_charset' ) );
	wp_send_json( array(
		'name' => 'WPLS Push',
		'gcm_sender_id' => wpls_push()->get_option( 'sender_id' )
	) );
}
exit;
