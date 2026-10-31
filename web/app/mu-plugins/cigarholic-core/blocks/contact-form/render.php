<?php
/**
 * Server render of cigarholic/contact-form (CNT-03).
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner content (unused).
 * @var WP_Block             $block      Block instance.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Contact\ContactForm;
use Cigarholic\Core\Security\Turnstile;

$cigarholic_heading    = isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '';
$cigarholic_button     = isset( $attributes['buttonLabel'] ) && '' !== $attributes['buttonLabel'] ? (string) $attributes['buttonLabel'] : __( 'Send message', 'cigarholic' );
$cigarholic_show_order = ! isset( $attributes['showOrderNumber'] ) || (bool) $attributes['showOrderNumber'];
$cigarholic_id         = wp_unique_id( 'cigarholic-contact-' );
$cigarholic_status     = isset( $_GET['contact'] ) ? sanitize_key( wp_unslash( $_GET['contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
$cigarholic_message    = '' !== $cigarholic_status ? ContactForm::status_message( $cigarholic_status ) : '';
$cigarholic_wrapper    = get_block_wrapper_attributes(
	array(
		'class' => 'cigarholic-contact',
		'id'    => 'cigarholic-contact',
	)
);
?>
<div <?php echo $cigarholic_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>>
	<?php if ( '' !== $cigarholic_heading ) : ?>
		<h2 class="cigarholic-contact__heading" id="<?php echo esc_attr( $cigarholic_id ); ?>-heading"><?php echo esc_html( $cigarholic_heading ); ?></h2>
	<?php endif; ?>
	<form class="cigarholic-contact__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		data-endpoint="<?php echo esc_url( rest_url( 'cigarholic/v1/contact' ) ); ?>"
		aria-labelledby="<?php echo esc_attr( $cigarholic_id ); ?>-heading" novalidate>
		<input type="hidden" name="action" value="<?php echo esc_attr( ContactForm::ACTION ); ?>">
		<div class="cigarholic-contact__field">
			<label for="<?php echo esc_attr( $cigarholic_id ); ?>-name"><?php esc_html_e( 'Your name', 'cigarholic' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $cigarholic_id ); ?>-name" name="name" required maxlength="120" autocomplete="name">
		</div>
		<div class="cigarholic-contact__field">
			<label for="<?php echo esc_attr( $cigarholic_id ); ?>-email"><?php esc_html_e( 'Email address', 'cigarholic' ); ?></label>
			<input type="email" id="<?php echo esc_attr( $cigarholic_id ); ?>-email" name="email" required autocomplete="email" inputmode="email">
		</div>
		<?php if ( $cigarholic_show_order ) : ?>
			<div class="cigarholic-contact__field">
				<label for="<?php echo esc_attr( $cigarholic_id ); ?>-order"><?php esc_html_e( 'Order number (optional)', 'cigarholic' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $cigarholic_id ); ?>-order" name="order_number" maxlength="20" inputmode="numeric" autocomplete="off">
			</div>
		<?php endif; ?>
		<div class="cigarholic-contact__field">
			<label for="<?php echo esc_attr( $cigarholic_id ); ?>-message"><?php esc_html_e( 'Message', 'cigarholic' ); ?></label>
			<textarea id="<?php echo esc_attr( $cigarholic_id ); ?>-message" name="message" required rows="6" maxlength="<?php echo esc_attr( (string) ContactForm::MAX_MESSAGE ); ?>"></textarea>
		</div>
		<div class="cigarholic-contact__honeypot" aria-hidden="true">
			<label for="<?php echo esc_attr( $cigarholic_id ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'cigarholic' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $cigarholic_id ); ?>-website" name="<?php echo esc_attr( ContactForm::HONEYPOT ); ?>" tabindex="-1" autocomplete="off">
		</div>
		<?php echo Turnstile::widget( 'contact' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Turnstile::widget(). ?>
		<p class="cigarholic-contact__status" role="status" aria-live="polite"<?php echo '' === $cigarholic_message ? '' : ' data-state="' . ( 'ok' === $cigarholic_status ? 'ok' : 'error' ) . '"'; ?>><?php echo esc_html( $cigarholic_message ); ?></p>
		<button class="cigarholic-contact__button wp-element-button" type="submit"><?php echo esc_html( $cigarholic_button ); ?></button>
		<p class="cigarholic-contact__note"><?php esc_html_e( 'We reply within one business day. Your message is emailed to our support team and not stored on this website.', 'cigarholic' ); ?></p>
	</form>
</div>
