<?php

require_once rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) . '/wp-load.php';

$current_user     = wp_get_current_user();
$role_check_admin = in_array( 'administrator', $current_user->roles );

// Only allow uploads from administrators
if ( $role_check_admin ) {

	// Disable PHP warning in order to produce a consistent response.
	error_reporting(E_ERROR | E_PARSE);

	$response = (object) array();

	$upload_dir      = wp_upload_dir();
	$deploy_path     = "{$upload_dir['basedir']}/deploy/";
	$deploy_path_url = "{$upload_dir['baseurl']}/deploy/";

	// If directory not create, then create it
	if ( !file_exists( $deploy_path ) ) {
		mkdir( $deploy_path, 0777, true );
	}

	$upload_file     = "$deploy_path{$_FILES['file']['name']}";
	$upload_file_url = "$deploy_path_url{$_FILES['file']['name']}";

	if ( move_uploaded_file( $_FILES['file']['tmp_name'], $upload_file ) ) {
		$response->response = "Success";
		$response->url      = $upload_file_url;
	} else {
		$response->response = "Error";
	}

	echo json_encode( $response );
}
