<?php

require_once rtrim( $_SERVER['DOCUMENT_ROOT'], '/' ) . '/wp-load.php';

$current_user     = wp_get_current_user();
$role_check_admin = in_array( 'administrator', $current_user->roles );

if ( $role_check_admin ) {

	$upload_dir = wp_upload_dir();

	$upload_file     = $upload_dir['basedir'] . '/deploy' . '/' . $_FILES['file']['name'];
	$upload_file_url = $upload_dir['baseurl'] . '/deploy' . '/' . $_FILES['file']['name'];

	if ( move_uploaded_file( $_FILES['file']['tmp_name'], $upload_file ) ) {
		echo '{"response":"Success","url":"' . $upload_file_url . '"}';
	} else {
		echo '{"response":"Error"}';
	}
}
