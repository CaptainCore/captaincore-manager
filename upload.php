<?php

require_once rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) . '/wp-load.php';

header( 'Content-Type: application/json' );

$response = (object) [ 'response' => 'Error' ];

// Only allow uploads from administrators.
if ( ! current_user_can( 'manage_options' ) ) {
	status_header( 403 );
	echo json_encode( $response );
	exit;
}

// CSRF protection: require a valid REST nonce (sent by the client as the
// X-WP-Nonce header, or a _wpnonce field as a fallback).
$nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? ( $_REQUEST['_wpnonce'] ?? '' );
if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
	status_header( 403 );
	echo json_encode( $response );
	exit;
}

if ( empty( $_FILES['file']['name'] ) ) {
	echo json_encode( $response );
	exit;
}

// Disable PHP warning in order to produce a consistent response.
error_reporting( E_ERROR | E_PARSE );

// Normalize the filename and allow only what the one consumer sends — the
// deploy directory is web-accessible, so anything executable landing there
// would be RCE. The Add plugin/theme dialog uploads .zip archives and nothing
// else, so an allowlist is both correct and narrower than enumerating every
// dangerous extension.
$filename  = sanitize_file_name( $_FILES['file']['name'] );
$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
$allowed   = [ 'zip' => 'application/zip' ];
if ( ! isset( $allowed[ $extension ] ) ) {
	$response->response = 'Invalid file type';
	echo json_encode( $response );
	exit;
}

// Confirm the contents match the name, so the extension is not the only check.
$checked = wp_check_filetype_and_ext( $_FILES['file']['tmp_name'], $filename, $allowed );
if ( empty( $checked['ext'] ) || ! isset( $allowed[ $checked['ext'] ] ) ) {
	$response->response = 'Invalid file type';
	echo json_encode( $response );
	exit;
}

$upload_dir      = wp_upload_dir();
$deploy_path     = "{$upload_dir['basedir']}/deploy/";
$deploy_path_url = "{$upload_dir['baseurl']}/deploy/";

// Create the directory if needed, and drop a guard that disables PHP execution
// within it as defense-in-depth against any file that slips past the allowlist.
if ( ! file_exists( $deploy_path ) ) {
	mkdir( $deploy_path, 0755, true );
}
// php_flag is a mod_php directive: under PHP-FPM Apache treats it as an invalid
// command and returns 500 for this whole directory, and nginx ignores .htaccess
// entirely. The FilesMatch block is portable, so the guard is written without
// php_flag - and rewritten if an earlier build left the broken version behind.
$htaccess       = "{$deploy_path}.htaccess";
$htaccess_rules = "<FilesMatch \"\\.(php|php3|php4|php5|php7|phps|phtml|pht|phar)$\">\n\tRequire all denied\n</FilesMatch>\n";
if ( ! file_exists( $htaccess ) || strpos( (string) file_get_contents( $htaccess ), 'php_flag' ) !== false ) {
	file_put_contents( $htaccess, $htaccess_rules );
}

$upload_file     = "{$deploy_path}{$filename}";
$upload_file_url = "{$deploy_path_url}{$filename}";

if ( move_uploaded_file( $_FILES['file']['tmp_name'], $upload_file ) ) {
	$response->response = 'Success';
	$response->url      = $upload_file_url;
}

echo json_encode( $response );
