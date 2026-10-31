<?php
/**
 * Server render of cigarholic/signup-form (CNT-04).
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner content (unused).
 * @var WP_Block             $block      Block instance.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Security\Turnstile;
use Cigarholic\Core\Signups\Signups;

$cigarholic_heading = isset( $attributes['heading'] ) ? (string) $attributes['heading'] : '';
$cigarholic_button  = isset( $attributes['buttonLabel'] ) && '' !== $attributes['buttonLabel'] ? (string) $attributes['buttonLabel'] : __( 'Notify me', 'cigarholic' );
$cigarholic_source  = isset( $attributes['source'] ) ? sanitize_key( (string) $attributes['source'] ) : Signups::DEFAULT_SOURCE;
$cigarholic_id      = wp_unique_id( 'cigarholic-signup-' );

// Status after the no-JavaScript form POST (?signup=ok|invalid_email|...).
$cigarholic_status  = isset( $_GET['signup'] ) ? sanitize_key( wp_unslash( $_GET['signup'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
$cigarholic_message = '' !== $cigarholic_status ? Signups::status_message( $cigarholic_status ) : '';
$cigarholic_is_ok   = 'ok' === $cigarholic_status;

$cigarholic_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'cigarholic-signup',
		'id'    => 'cigarholic-signup',
	)
);
?>
<div <?php echo $cigarholic_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>>
	<?php if ( '' !== $cigarholic_heading ) : ?>
		<h2 class="cigarholic-signup__heading" id="<?php echo esc_attr( $cigarholic_id ); ?>-heading"><?php echo esc_html( $cigarholic_heading ); ?></h2>
	<?php endif; ?>
	<form class="cigarholic-signup__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		data-endpoint="<?php echo esc_url( rest_url( Signups::REST_NAMESPACE . '/signups' ) ); ?>"
		aria-labelledby="<?php echo esc_attr( $cigarholic_id ); ?>-heading" novalidate>
		<input type="hidden" name="action" value="<?php echo esc_attr( Signups::ACTION ); ?>">
		<input type="hidden" name="source" value="<?php echo esc_attr( $cigarholic_source ); ?>">
		<div class="cigarholic-signup__row">
			<label class="cigarholic-signup__label" for="<?php echo esc_attr( $cigarholic_id ); ?>-email"><?php esc_html_e( 'Email address', 'cigarholic' ); ?></label>
			<input class="cigarholic-signup__input" type="email" id="<?php echo esc_attr( $cigarholic_id ); ?>-email" name="email" required autocomplete="email" inputmode="email" placeholder="<?php esc_attr_e( 'you@example.com', 'cigarholic' ); ?>">
			<button class="cigarholic-signup__button wp-element-button" type="submit"><?php echo esc_html( $cigarholic_button ); ?></button>
		</div>
		<div class="cigarholic-signup__honeypot" aria-hidden="true">
			<label for="<?php echo esc_attr( $cigarholic_id ); ?>-website"><?php esc_html_e( 'Leave this field empty', 'cigarholic' ); ?></label>
			<input type="text" id="<?php echo esc_attr( $cigarholic_id ); ?>-website" name="<?php echo esc_attr( Signups::HONEYPOT ); ?>" tabindex="-1" autocomplete="off">
		</div>
		<?php echo Turnstile::widget( 'signup' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Turnstile::widget(). ?>
		<p class="cigarholic-signup__status" role="status" aria-live="polite"<?php echo '' === $cigarholic_message ? '' : ' data-state="' . ( $cigarholic_is_ok ? 'ok' : 'error' ) . '"'; ?>><?php echo esc_html( $cigarholic_message ); ?></p>
		<p class="cigarholic-signup__consent"><?php esc_html_e( 'We only email you about the store opening and new pieces. Unsubscribe any time.', 'cigarholic' ); ?></p>
	</form>
</div>
