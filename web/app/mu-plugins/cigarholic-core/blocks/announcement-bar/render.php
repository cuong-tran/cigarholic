<?php
/**
 * Server render of cigarholic/announcement-bar (NAV-02).
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner content (unused).
 * @var WP_Block             $block      Block instance.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Storefront\AnnouncementBar;

$cigarholic_settings = AnnouncementBar::settings();
if ( ! AnnouncementBar::is_active_now() ) {
	return;
}
$cigarholic_wrapper = get_block_wrapper_attributes(
	array(
		'class'      => 'cigarholic-announcement-bar',
		'role'       => 'region',
		'aria-label' => __( 'Announcement', 'cigarholic' ),
	)
);
?>
<div <?php echo $cigarholic_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by get_block_wrapper_attributes(). ?>>
	<p class="cigarholic-announcement-bar__text">
		<?php if ( '' !== $cigarholic_settings['url'] ) : ?>
			<a href="<?php echo esc_url( $cigarholic_settings['url'] ); ?>"><?php echo esc_html( $cigarholic_settings['text'] ); ?></a>
		<?php else : ?>
			<?php echo esc_html( $cigarholic_settings['text'] ); ?>
		<?php endif; ?>
	</p>
</div>
