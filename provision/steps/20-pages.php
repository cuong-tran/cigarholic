<?php
/**
 * Provisioning step: core pages (CNT-02) and the reading settings behind / and /journal/.
 *
 * Policy and information pages are created once as drafts with a clearly marked placeholder
 * structure (the headings a real text needs); the owner reviews and publishes them (L-08). Existing
 * pages are never overwritten. The published "Home" page carries the theme's front-page template at
 * / and the published "Journal" page is the posts page at /journal/ (ADR 0002). The terms and
 * privacy pages are wired to checkout here (CHK-05).
 *
 * Feature IDs: FND-03, CNT-02, CHK-05, SEO-03.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	/**
	 * Paragraph block.
	 *
	 * @param string $text Paragraph text (plain, no HTML).
	 * @return string
	 */
	$paragraph = static fn( string $text ): string => "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->";

	/**
	 * Heading block.
	 *
	 * @param string $text Heading text.
	 * @return string
	 */
	$heading = static fn( string $text ): string => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->";

	/**
	 * Draft placeholder: the review notice, then one section per heading with a one-line prompt.
	 *
	 * @param string                $intro    First paragraph after the notice.
	 * @param array<string, string> $sections Heading => what the section must say.
	 * @return string Block content.
	 */
	$draft = static function ( string $intro, array $sections ) use ( $paragraph, $heading ): string {
		$blocks = array(
			"<!-- wp:paragraph {\"className\":\"draft-notice\"} -->\n<p class=\"draft-notice\"><strong>DRAFT: for owner review, see docs/FEATURES.md L-08.</strong> Placeholder structure written by provisioning; replace every section before publishing. Cigarholic sells humidors, cigar boxes and accessories, never cigars or tobacco. Everything is handcrafted in Vietnam and shipped from our Texas warehouse.</p>\n<!-- /wp:paragraph -->",
			$paragraph( $intro ),
		);
		foreach ( $sections as $title => $prompt ) {
			$blocks[] = $heading( $title );
			$blocks[] = $paragraph( $prompt );
		}
		return implode( "\n\n", $blocks );
	};

	$company = 'HuyLinh LLC, 7901 4th St N STE 300, St Petersburg, FL 33702. US warehouse: 8388 W Sam Houston Pkwy S #172, Houston, TX 77072. Phone +1 813 602 7585, support@cigarholic.com.';

	$ctx->page(
		'about',
		'Our Craft',
		$draft(
			'Who makes Cigarholic humidors, where and how.',
			array(
				'Our workshop'                              => 'The artisans, the town, how long the workshop has made humidors and boxes.',
				'The woods we use'                          => 'Exterior woods, Spanish cedar lining, veneers; how each wood behaves in a humidor.',
				'How a humidor is made'                     => 'From seasoned timber to joinery, seal, finish and quality check.',
				'Handcrafted in Vietnam, shipped from Texas' => 'Origin stated plainly; stock held and shipped from the US warehouse.',
				'HuyLinh LLC'                               => $company,
			)
		),
		'draft'
	);

	$ctx->page(
		'contact',
		'Contact',
		$draft(
			'How to reach us about an order, a product or a custom piece.',
			array(
				'Email and phone'  => 'support@cigarholic.com, +1 813 602 7585 (US Eastern business hours). Replies within one business day.',
				'Mailing address'  => $company,
				'Trade and press'  => 'Wholesale, corporate gifts and press requests: same address, mention the subject.',
			)
		) . "\n\n<!-- wp:cigarholic/contact-form {\"heading\":\"Send us a message\"} /-->",
		'draft'
	);

	$ctx->page(
		'faq',
		'FAQ',
		$draft(
			'Answers to the questions we hear most about choosing, seasoning and caring for a humidor.',
			array(
				'Choosing a humidor'   => 'Capacity, desktop versus cabinet versus travel, lining, humidification, hygrometer.',
				'Seasoning a humidor'  => 'Why and how to season a new humidor before first use; how long it takes.',
				'Humidity and care'    => 'Target humidity, refilling humidification, cleaning, what to avoid.',
				'Ordering and payment' => 'Payment methods, order changes, gift messages, sales tax.',
				'Shipping'             => 'Where we ship, processing time, delivery estimates, tracking; link to the shipping policy.',
				'Returns and warranty' => 'Return window and warranty in one paragraph each; link to the full policies.',
			)
		),
		'draft'
	);

	$ctx->page(
		'shipping-policy',
		'Shipping Policy',
		$draft(
			'Where and how we ship, and what it costs.',
			array(
				'Where we ship'                         => 'Contiguous United States at launch. Alaska, Hawaii and US territories are not served yet (D-06).',
				'Processing time'                       => 'Business days from order to dispatch from the Texas warehouse; personalised items take longer.',
				'Shipping rates'                        => 'Humidors ship free; accessories carry a flat rate (L-07). Rates shown at checkout.',
				'Delivery estimates'                    => 'Carrier and transit times by region; signature requirements for large items.',
				'Tracking'                              => 'Tracking number by email when the parcel leaves the warehouse.',
				'Damaged or lost in transit'            => 'What to do within 48 hours of delivery; photos; replacement or refund.',
				'Address changes and failed deliveries' => 'How to change an address before dispatch; returned parcels.',
			)
		),
		'draft'
	);

	$ctx->page(
		'returns-refunds',
		'Returns & Refunds',
		$draft(
			'Our return window, who pays return shipping and how refunds work.',
			array(
				'Return window'              => 'Number of days from delivery (L-08).',
				'Condition of returned items' => 'Unused, unseasoned, in original packaging with all accessories.',
				'Return shipping'            => 'Who pays, how a label is issued, carrier for oversized items (L-08).',
				'Refunds'                    => 'Refund to the original payment method within N business days of receipt; what is deducted.',
				'Exchanges'                  => 'Wrong size or finish: how an exchange works.',
				'Personalised and custom items' => 'Engraved or made-to-order pieces: returnable only if defective (L-08).',
				'How to start a return'      => 'Email support@cigarholic.com with the order number; what happens next.',
			)
		),
		'draft'
	);

	$ctx->page(
		'warranty',
		'Warranty',
		$draft(
			'What our warranty covers on humidors, cigar boxes and accessories.',
			array(
				'What is covered'      => 'Workmanship and materials: joinery, hinges, seal, lining, finish.',
				'Warranty period'      => 'Length from delivery date (L-08); registration not required, order number is proof.',
				'What is not covered'  => 'Normal wear, humidity damage from misuse, modifications, accidents, hygrometer calibration.',
				'How to make a claim'  => 'Email with photos and order number; repair, replacement or refund at our choice.',
				'Limitations'          => 'State law rights are not affected; implied warranty disclaimers as advised by counsel.',
			)
		),
		'draft'
	);

	// WordPress already creates a draft Privacy Policy with its suggested text; the helper keeps it.
	$privacy_id = $ctx->page(
		'privacy-policy',
		'Privacy Policy',
		$draft(
			'What personal data we collect, why, and the choices you have.',
			array(
				'Who we are'                    => $company,
				'What we collect'               => 'Order and contact data, account data, newsletter signups, analytics and payment data handled by Stripe and PayPal.',
				'Why we use it'                 => 'Fulfilling orders, support, legal obligations, marketing with consent.',
				'Who we share it with'          => 'Payment processors, carriers, email provider, analytics; never sold.',
				'Cookies'                       => 'Consent banner and categories (CMP-03).',
				'Your rights and choices'       => 'Access, correction, deletion, unsubscribe; state privacy rights.',
				'Contact'                       => 'support@cigarholic.com for privacy requests.',
			)
		),
		'draft'
	);

	$terms_id = $ctx->page(
		'terms-of-service',
		'Terms of Service',
		$draft(
			'The terms that apply when you buy from Cigarholic.',
			array(
				'Agreement'                   => 'Who the contract is with (HuyLinh LLC) and acceptance at checkout.',
				'Products and descriptions'   => 'Natural wood variation, photos, specifications, no tobacco products sold.',
				'Orders, pricing and payment' => 'Order acceptance, pricing errors, taxes, payment methods.',
				'Shipping and risk of loss'   => 'Link to the shipping policy; when risk passes.',
				'Returns and warranty'        => 'Links to the returns and warranty pages.',
				'Disclaimers and limitation of liability' => 'As advised by counsel.',
				'Governing law'               => 'Florida law; dispute resolution.',
				'Contact'                     => $company,
			)
		),
		'draft'
	);

	$ctx->page(
		'accessibility-statement',
		'Accessibility Statement',
		$draft(
			'Our commitment to a store everyone can use.',
			array(
				'Our commitment'     => 'WCAG 2.2 level AA as the target for every page and template (CMP-04).',
				'Measures we take'   => 'Keyboard navigation, contrast, alternative text, form labels, testing with assistive technology.',
				'Known limitations'  => 'Anything not yet conformant and when it will be fixed.',
				'Feedback'           => 'support@cigarholic.com or +1 813 602 7585; we answer within N business days.',
			)
		),
		'draft'
	);

	// Reading settings. The block theme renders front-page.html for the front page and home.html
	// for the posts page, which WordPress only serves at /journal/ when the front page is a page.
	$home_id = $ctx->page(
		'home',
		'Home',
		$paragraph( 'The front page is designed in the theme template front-page.html; this page only holds the URL. Text entered here is not displayed.' )
	);
	$journal_id = $ctx->page(
		'journal',
		'Journal',
		$paragraph( 'The journal lists the posts; its layout is the theme template home.html. Text entered here is not displayed.' )
	);
	$ctx->option( 'show_on_front', 'page' );
	if ( $home_id > 0 ) {
		$ctx->option( 'page_on_front', (string) $home_id );
	}
	if ( $journal_id > 0 ) {
		$ctx->option( 'page_for_posts', (string) $journal_id );
	}

	// Checkout links (CHK-05) and the privacy page WordPress itself uses.
	if ( $terms_id > 0 ) {
		$ctx->option( 'woocommerce_terms_page_id', (string) $terms_id );
	}
	if ( $privacy_id > 0 ) {
		$ctx->option( 'wp_page_for_privacy_policy', (string) $privacy_id );
	}

	// WordPress's installer leaves a published "Sample Page"; it has no place on a store.
	$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
	if ( $sample instanceof WP_Post && 'Sample Page' === $sample->post_title && 'trash' !== $sample->post_status ) {
		if ( ! $ctx->is_dry_run() ) {
			wp_trash_post( $sample->ID );
		}
		$ctx->changed( 'page sample-page trashed' );
	} else {
		$ctx->log( 'ok: no sample page' );
	}
};
