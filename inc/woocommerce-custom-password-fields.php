<?php

add_action( 'woocommerce_edit_account_form', 'captaincore_woocommerce_edit_account_form' );
function captaincore_woocommerce_edit_account_form() {

  $user_id = get_current_user_id();
  $user = get_userdata( $user_id );

  if ( !$user )
    return;

  ?>

  <fieldset>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="captaincore_password_1"><?php _e( 'New password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="captaincore_password_1" id="captaincore_password_1" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="captaincore_password_2"><?php _e( 'Confirm new password', 'woocommerce' ); ?></label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="captaincore_password_2" id="captaincore_password_2" />
		</p>
  </fieldset>

  <?php
}

add_action( 'woocommerce_save_account_details', 'captaincore_woocommerce_save_account_details' );
function captaincore_woocommerce_save_account_details( $user_id ) {
	if ( ( ! empty( $_POST['captaincore_password_1'] ) && ! empty( $_POST['captaincore_password_2']) ) && $_POST['captaincore_password_1'] == $_POST['captaincore_password_2'] ) {
		$user = wp_update_user( array( 'ID' => $user_id, 'user_pass' => $_POST['captaincore_password_1'] ) );
	}
}

add_action( 'woocommerce_save_account_details_errors','captaincore_woocommerce_validate_custom_field',  10,2 );
function captaincore_woocommerce_validate_custom_field(&$args, &$user) {
	if ( $_POST['captaincore_password_1'] !=  $_POST['captaincore_password_2']) {
    wc_add_notice( __( 'New passwords do not match.', 'woocommerce' ), 'error' );
	}
}
