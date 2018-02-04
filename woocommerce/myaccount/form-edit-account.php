<?php
/**
 * Edit account form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @author  WooThemes
 * @package WooCommerce/Templates
 * @version 2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_edit_account_form' ); ?>
<script>
/* global wp, pwsL10n, wc_password_strength_meter_params */
jQuery( function( $ ) {

	/**
	 * Password Strength Meter class.
	 */
	var wc_password_strength_meter = {

		/**
		 * Initialize strength meter actions.
		 */
		init: function() {
			$( document.body )
				.on( 'keyup change', 'form.register #reg_password, form.checkout #account_password, form.edit-account #anchor_password_1, form.lost_reset_password #anchor_password_1', this.strengthMeter );
			$( 'form.checkout #createaccount' ).change();
		},

		/**
		 * Strength Meter.
		 */
		strengthMeter: function() {
			var wrapper    = $( 'form.register, form.checkout, form.edit-account, form.lost_reset_password' ),
				submit     = $( 'input[type="submit"]', wrapper ),
				field      = $( '#reg_password, #account_password, #anchor_password_1', wrapper ),
				strength   = 1,
				fieldValue = field.val();

			wc_password_strength_meter.includeMeter( wrapper, field );

			strength = wc_password_strength_meter.checkPasswordStrength( wrapper, field );

			if ( fieldValue.length > 0 && strength < wc_password_strength_meter_params.min_password_strength && ! wrapper.is( 'form.checkout' ) ) {
				submit.attr( 'disabled', 'disabled' ).addClass( 'disabled' );
			} else {
				submit.removeAttr( 'disabled', 'disabled' ).removeClass( 'disabled' );
			}
		},

		/**
		 * Include meter HTML.
		 *
		 * @param {Object} wrapper
		 * @param {Object} field
		 */
		includeMeter: function( wrapper, field ) {
			var meter = wrapper.find( '.woocommerce-password-strength' );

			if ( '' === field.val() ) {
				meter.remove();
				$( document.body ).trigger( 'wc-password-strength-removed' );
			} else if ( 0 === meter.length ) {
				field.after( '<div class="woocommerce-password-strength" aria-live="polite"></div>' );
				$( document.body ).trigger( 'wc-password-strength-added' );
			}
		},

		/**
		 * Check password strength.
		 *
		 * @param {Object} field
		 *
		 * @return {Int}
		 */
		checkPasswordStrength: function( wrapper, field ) {
			var meter     = wrapper.find( '.woocommerce-password-strength' );
			var hint      = wrapper.find( '.woocommerce-password-hint' );
			var hint_html = '<small class="woocommerce-password-hint">' + wc_password_strength_meter_params.i18n_password_hint + '</small>';
			var strength  = wp.passwordStrength.meter( field.val(), wp.passwordStrength.userInputBlacklist() );
			var error     = '';

			// Reset
			meter.removeClass( 'short bad good strong' );
			hint.remove();

			// Error to append
			if ( strength < wc_password_strength_meter_params.min_password_strength ) {
				error = ' - ' + wc_password_strength_meter_params.i18n_password_error;
			}

			switch ( strength ) {
				case 0 :
					meter.addClass( 'short' ).html( pwsL10n['short'] + error );
					meter.after( hint_html );
					break;
				case 1 :
					meter.addClass( 'bad' ).html( pwsL10n.bad + error );
					meter.after( hint_html );
					break;
				case 2 :
					meter.addClass( 'bad' ).html( pwsL10n.bad + error );
					meter.after( hint_html );
					break;
				case 3 :
					meter.addClass( 'good' ).html( pwsL10n.good + error );
					break;
				case 4 :
					meter.addClass( 'strong' ).html( pwsL10n.strong + error );
					break;
				case 5 :
					meter.addClass( 'short' ).html( pwsL10n.mismatch );
					break;
			}

			return strength;
		}
	};

	wc_password_strength_meter.init();
});
</script>
<form class="woocommerce-EditAccountForm edit-account" action="" method="post">

	<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

	<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
		<label for="account_first_name"><?php _e( 'First name', 'woocommerce' ); ?> <span class="required">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" value="<?php echo esc_attr( $user->first_name ); ?>" />
	</p>
	<p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
		<label for="account_last_name"><?php _e( 'Last name', 'woocommerce' ); ?> <span class="required">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" value="<?php echo esc_attr( $user->last_name ); ?>" />
	</p>
	<div class="clear"></div>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_email"><?php _e( 'Email address', 'woocommerce' ); ?> <span class="required">*</span></label>
		<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" value="<?php echo esc_attr( $user->user_email ); ?>" />
	</p>
<?php /*
	<fieldset>
		<legend><?php _e( 'Password change', 'woocommerce' ); ?></legend>

		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="password_current"><?php _e( 'Current password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="password_1"><?php _e( 'New password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="password_2"><?php _e( 'Confirm new password', 'woocommerce' ); ?></label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" />
		</p>
	</fieldset> */ ?>
	<div class="clear"></div>

	<?php do_action( 'woocommerce_edit_account_form' ); ?>

	<p>
		<?php wp_nonce_field( 'save_account_details' ); ?>
		<input type="submit" class="woocommerce-Button button" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>" />
		<input type="hidden" name="action" value="save_account_details" />
	</p>

	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
