<?php
/**
 * Edit account form — Elvane copy of WooCommerce 11.0.0's template.
 *
 * Changes: email is optional; the password section depends on whether the
 * customer just confirmed their phone by OTP (see includes/account-password.php).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook - woocommerce_before_edit_account_form.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_before_edit_account_form' );
?>


<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

	<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

	<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
		<label for="account_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $user->first_name ); ?>" aria-required="true" />
	</p>
	<p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
		<label for="account_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $user->last_name ); ?>" aria-required="true" />
	</p>
	<div class="clear"></div>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_display_name"><?php esc_html_e( 'Display name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
		<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" />
		<span id="account_display_name_description"><em><?php echo wc_reviews_enabled() ? esc_html__( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ) : esc_html__( 'This will be how your name will be displayed in the account section', 'woocommerce' ); ?></em></span>
	</p>
	<div class="clear"></div>

	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?> <span class="elvane-optional"><?php esc_html_e( '(optional)', 'elvane-store' ); ?></span></label>
		<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" aria-describedby="account_email_description" value="<?php echo esc_attr( $user->user_email ); ?>" />
		<span id="account_email_description"><em><?php esc_html_e( 'For order emails, and to log in with email and password.', 'elvane-store' ); ?></em></span>
	</p>

	<?php
		/**
		 * Hook where additional fields should be rendered.
		 *
		 * @since 8.7.0
		 */
		do_action( 'woocommerce_edit_account_form_fields' );
	?>

	<?php
	$elvane_window  = elvane_otp_window_left( $user->ID );
	$elvane_has_own = elvane_has_own_password( $user->ID );
	$elvane_phone   = substr( preg_replace( '/\D/', '', (string) get_user_meta( $user->ID, 'phone_number', true ) ), -10 );
	$elvane_new_pw  = '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="password_1">' . esc_html__( 'New password', 'elvane-store' ) . '</label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" minlength="8" />
		</p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="password_2">' . esc_html__( 'Confirm new password', 'woocommerce' ) . '</label>
			<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" minlength="8" />
		</p>';
	?>
	<fieldset class="elvane-password">
		<legend><?php echo esc_html( $elvane_has_own ? __( 'Change password', 'elvane-store' ) : __( 'Password', 'elvane-store' ) ); ?></legend>

		<?php if ( $elvane_window ) : ?>
			<p class="elvane-password__note elvane-password__note--ok">
				<?php
				printf(
					/* translators: %d: minutes left */
					esc_html__( 'Number confirmed. Set your password below (within %d minutes) — no current password needed.', 'elvane-store' ),
					(int) ceil( $elvane_window / MINUTE_IN_SECONDS )
				);
				?>
			</p>
			<?php echo $elvane_new_pw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with escaped parts. ?>

		<?php elseif ( $elvane_has_own ) : ?>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password_current"><?php esc_html_e( 'Current password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
				<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="current-password" />
			</p>
			<?php echo $elvane_new_pw; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="elvane-password__note">
				<?php esc_html_e( 'Forgot your current password?', 'elvane-store' ); ?>
				<button type="button" class="elvane-password__link" data-elvane-reverify><?php esc_html_e( 'Verify with a code instead', 'elvane-store' ); ?></button>
			</p>

		<?php else : ?>
			<p class="elvane-password__note">
				<?php esc_html_e( 'You log in with a code sent to your phone. You can also set a password, to log in with your mobile number or email and password.', 'elvane-store' ); ?>
			</p>
			<p>
				<button type="button" class="button elvane-password__verify" data-elvane-reverify>
					<?php echo esc_html( $elvane_phone ? sprintf( /* translators: %s: last digits of the phone number */ __( 'Set a password — verify +91 ••••••%s', 'elvane-store' ), substr( $elvane_phone, -4 ) ) : __( 'Set a password — verify your mobile number', 'elvane-store' ) ); ?>
				</button>
			</p>
		<?php endif; ?>
	</fieldset>
	<div class="clear"></div>

	<?php
		/**
		 * My Account edit account form.
		 *
		 * @since 2.6.0
		 */
		do_action( 'woocommerce_edit_account_form' );
	?>

	<p>
		<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<button type="submit" class="woocommerce-Button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></button>
		<input type="hidden" name="action" value="save_account_details" />
	</p>

	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
