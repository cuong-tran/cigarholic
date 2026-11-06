<?php
/**
 * Provisioning step: SEOPress titles, metas, sitemaps and social cards (SEO-01).
 *
 * SEOPress (D-09) writes generic defaults on activation: archive titles without the site name
 * ("Shop -"), the post category sitemap but not the product categories, and no knowledge graph.
 * This step sets the keys the store relies on inside SEOPress's option arrays and keeps every other
 * key, so a setting the manager changes elsewhere in SEOPress survives a deploy. Titles and
 * descriptions of single products, pages and categories stay editable per item in the SEOPress
 * metabox; these are the templates used when an item has none.
 *
 * Feature IDs: SEO-01 (primary), SEO-02 (Organization), FND-03, MKT-02.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	if ( ! defined( 'SEOPRESS_VERSION' ) ) {
		$ctx->log( 'skip: SEOPress is not active' );
		return;
	}

	/**
	 * Merges values into one of SEOPress's option arrays, keeping the keys this step does not own.
	 * Nested arrays merge key by key (array_replace_recursive), so key order and unrelated entries
	 * stay as they are and a second run compares equal.
	 *
	 * @param string               $name   Option name.
	 * @param array<string, mixed> $values Keys to enforce.
	 * @param list<string>         $remove Top-level keys to remove (SEOPress treats a present key as "on").
	 * @return void
	 */
	$merge = static function ( string $name, array $values, array $remove = array() ) use ( $ctx ): void {
		$current = get_option( $name, array() );
		$current = is_array( $current ) ? $current : array();
		$wanted  = array_replace_recursive( $current, $values );
		foreach ( $remove as $key ) {
			unset( $wanted[ $key ] );
		}
		$ctx->option( $name, $wanted );
	};

	$title = '%%post_title%% %%sep%% %%sitetitle%%';
	$term  = '%%term_title%% %%current_pagination%% %%sep%% %%sitetitle%%';

	$merge(
		'seopress_titles_option_name',
		array(
			'seopress_titles_sep'                     => '|',
			'seopress_titles_home_site_title'         => 'Cigarholic | Handcrafted Wooden Humidors and Cigar Boxes',
			'seopress_titles_home_site_desc'          => 'Wooden humidors, cigar boxes and humidor accessories, handcrafted in Vietnam and shipped from our Texas warehouse to the contiguous US.',
			'seopress_titles_single_titles'           => array(
				'post'    => array(
					'title'       => $title,
					'description' => '%%post_excerpt%%',
				),
				'page'    => array(
					'title'       => $title,
					'description' => '%%post_excerpt%%',
				),
				'product' => array(
					'title'       => $title,
					'description' => '%%post_excerpt%%',
				),
			),
			// The shop is the product archive. SEOPress's default archive template drops the site name.
			'seopress_titles_archive_titles'          => array(
				'product' => array(
					'title'       => 'Shop Humidors and Cigar Boxes %%current_pagination%% %%sep%% %%sitetitle%%',
					'description' => 'Desktop, cabinet and travel humidors, cigar boxes and humidor accessories, handcrafted in Vietnam.',
				),
			),
			// Product categories are the collections customers search for. Tags, brands and post formats are
			// unused; occasions stay out of search results until CAT-14 makes them landing pages.
			'seopress_titles_tax_titles'              => array(
				'product_cat'   => array(
					'title'       => $term,
					'description' => '%%term_description%%',
				),
				'product_tag'   => array( 'noindex' => '1' ),
				'product_brand' => array( 'noindex' => '1' ),
				'post_format'   => array( 'noindex' => '1' ),
				'occasion'      => array( 'noindex' => '1' ),
			),
			'seopress_titles_archives_author_noindex' => '1',
			'seopress_titles_archives_date_noindex'   => '1',
			'seopress_titles_archives_search_title_noindex' => '1',
			'seopress_titles_attachments_noindex'     => '1',
		)
	);

	// XML sitemaps: pages, journal posts, products and product categories. The blog category and author
	// sitemaps go: no journal categories are used yet, and author archives answer 404 (OPS-07, #45).
	$merge(
		'seopress_xml_sitemap_option_name',
		array(
			'seopress_xml_sitemap_general_enable'  => '1',
			'seopress_xml_sitemap_img_enable'      => '1',
			'seopress_xml_sitemap_post_types_list' => array(
				'post'    => array( 'include' => '1' ),
				'page'    => array( 'include' => '1' ),
				'product' => array( 'include' => '1' ),
			),
			'seopress_xml_sitemap_taxonomies_list' => array(
				'product_cat' => array( 'include' => '1' ),
				'category'    => array( 'include' => '0' ),
			),
		),
		array( 'seopress_xml_sitemap_author_enable' )
	);

	// Cart, checkout (with order received) and account pages are never search results. WooCommerce marks
	// them noindex, but on a public site SEOPress removes that (seopress_robots_wc_pages) and prints
	// "index, follow" instead. Its own per-page noindex restores it and drops them from the sitemap.
	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $wc_page ) {
			$page_id = wc_get_page_id( $wc_page );
			if ( $page_id <= 0 ) {
				$ctx->log( sprintf( 'skip: no WooCommerce %s page', $wc_page ) );
				continue;
			}
			if ( 'yes' === get_post_meta( $page_id, '_seopress_robots_index', true ) ) {
				$ctx->log( sprintf( 'ok: %s page noindex', $wc_page ) );
				continue;
			}
			if ( ! $ctx->is_dry_run() ) {
				update_post_meta( $page_id, '_seopress_robots_index', 'yes' );
			}
			$ctx->changed( sprintf( 'noindex for the %s page (#%d)', $wc_page, $page_id ) );
		}
	}

	// Knowledge graph on the home page (SEO-02 Organization) from the company details the footer already
	// shows. No social profiles until the owner gives real ones (the footer links are placeholders).
	$logo_id = (int) get_option( 'site_logo' );
	$logo    = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	$merge(
		'seopress_social_option_name',
		array(
			'seopress_social_knowledge_type'         => 'OnlineStore',
			'seopress_social_knowledge_name'         => 'Cigarholic',
			'seopress_social_knowledge_legal_name'   => 'HuyLinh LLC',
			'seopress_social_knowledge_desc'         => 'Handcrafted wooden humidors, cigar boxes and humidor accessories, made in Vietnam. Cigarholic does not sell cigars, tobacco or nicotine products.',
			'seopress_social_knowledge_img'          => $logo,
			'seopress_social_knowledge_phone'        => '+1-813-602-7585',
			'seopress_social_knowledge_email'        => 'support@cigarholic.com',
			'seopress_social_knowledge_contact_type' => 'customer support',
			'seopress_social_knowledge_street'       => '7901 4th St N STE 300',
			'seopress_social_knowledge_locality'     => 'St Petersburg',
			'seopress_social_knowledge_region'       => 'FL',
			'seopress_social_knowledge_postal_code'  => '33702',
			'seopress_social_knowledge_country'      => 'US',
			'seopress_social_facebook_og'            => '1',
			'seopress_social_twitter_card'           => '1',
			'seopress_social_twitter_card_og'        => '1',
			'seopress_social_twitter_card_img_size'  => 'large',
		)
	);

	// GA4 comes from Google Analytics for WooCommerce (MKT-02), never from SEOPress's own module, so
	// a measurement ID typed into SEOPress cannot double-count. Its setup wizard is not needed.
	$merge( 'seopress_toggle', array( 'toggle-google-analytics' => '0' ) );
	$merge( 'seopress_notices', array( 'notice-wizard' => '1' ) );
};
