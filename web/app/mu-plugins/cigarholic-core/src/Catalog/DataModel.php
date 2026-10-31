<?php
/**
 * Catalog data model: categories, occasions, global attributes, specification and compliance fields.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Catalog;

use Cigarholic\Core\Feature;
use WC_Product;
use WP_Term;

/**
 * The product data every other catalog feature reads (ADR 0003), set once and the same on every product:
 *
 * - product categories with fixed slugs (CAT-02; URLs stay /collections/<slug>/, ADR 0002),
 * - the `occasion` taxonomy for gift merchandising,
 * - global WooCommerce attributes for everything filterable (CAT-03),
 * - numeric specification meta (`_ch_*`, inches) with a "Specifications" product data tab,
 * - private compliance meta (CMP-11: species, harvest country, composite wood, HTS code) that is never
 *   rendered on the storefront, and the per-product Prop 65 toggle (CMP-06, default off per D-08).
 *
 * Everything is ensured idempotently on `init` whenever the model fingerprint changes, and on demand by
 * `wp cigarholic catalog ensure`, `wp cigarholic seed` and the tests.
 *
 * Feature IDs: CAT-03 (primary), CAT-02, CMP-11, CMP-06.
 */
final class DataModel implements Feature {

	public const TAXONOMY     = 'occasion';
	public const OPTION       = 'cigarholic_catalog_model';
	public const VERSION      = '1';
	public const NONCE_ACTION = 'cigarholic_specs_save';
	public const NONCE_FIELD  = 'cigarholic_specs_nonce';

	/**
	 * Product categories: slug => name. Flat, slugs fixed before product entry (ADR 0002).
	 */
	public const CATEGORIES = array(
		'desktop-humidors'    => 'Desktop Humidors',
		'cabinet-humidors'    => 'Cabinet Humidors',
		'travel-humidors'     => 'Travel Humidors',
		'cigar-boxes'         => 'Cigar Boxes',
		'humidor-accessories' => 'Humidor Accessories',
		'gift-sets'           => 'Gift Sets',
	);

	/**
	 * Categories whose products are humidors and need a cigar capacity.
	 */
	public const HUMIDOR_CATEGORIES = array( 'desktop-humidors', 'cabinet-humidors', 'travel-humidors' );

	/**
	 * Occasion terms: slug => name.
	 */
	public const OCCASIONS = array(
		'fathers-day' => "Father's Day",
		'groomsmen'   => 'Groomsmen',
		'corporate'   => 'Corporate',
		'holiday'     => 'Holiday',
		'anniversary' => 'Anniversary',
		'retirement'  => 'Retirement',
	);

	/**
	 * Global attributes: slug => label and terms (slug => name). Labels sanitize to the slug, so the
	 * WooCommerce CSV importer ("Attribute N name") finds the existing attribute. Term lists are
	 * placeholders until the owner's material sheet arrives (OWNER-ACTIONS B12).
	 */
	public const ATTRIBUTES = array(
		'capacity-range'  => array(
			'label' => 'Capacity range',
			'terms' => array(
				'up-to-25' => 'Up to 25 cigars',
				'25-50'    => '25-50 cigars',
				'50-100'   => '50-100 cigars',
				'100-200'  => '100-200 cigars',
				'200-plus' => '200+ cigars',
			),
		),
		'exterior-wood'   => array(
			'label' => 'Exterior wood',
			'terms' => array(
				'walnut'   => 'Walnut',
				'mahogany' => 'Mahogany',
				'oak'      => 'Oak',
				'cherry'   => 'Cherry',
				'maple'    => 'Maple',
				'acacia'   => 'Acacia',
			),
		),
		'interior-lining' => array(
			'label' => 'Interior lining',
			'terms' => array(
				'spanish-cedar' => 'Spanish cedar',
				'mahogany'      => 'Mahogany',
				'okoume'        => 'Okoume',
			),
		),
		'humidification'  => array(
			'label' => 'Humidification',
			'terms' => array(
				'humidifier-jar' => 'Humidifier jar',
				'humidifier-bar' => 'Humidifier bar',
				'boveda-packs'   => 'Boveda packs',
				'electronic'     => 'Electronic',
				'none'           => 'None',
			),
		),
		'hygrometer'      => array(
			'label' => 'Hygrometer',
			'terms' => array(
				'analog'  => 'Analog',
				'digital' => 'Digital',
				'none'    => 'None',
			),
		),
		'finish'          => array(
			'label' => 'Finish',
			'terms' => array(
				'matte-lacquer' => 'Matte lacquer',
				'gloss-lacquer' => 'Gloss lacquer',
				'oil'           => 'Oil',
				'unfinished'    => 'Unfinished',
			),
		),
		'lock'            => array(
			'label' => 'Lock',
			'terms' => array(
				'none'        => 'None',
				'key-lock'    => 'Key lock',
				'combination' => 'Combination',
			),
		),
		'seal'            => array(
			'label' => 'Seal',
			'terms' => array(
				'spanish-cedar-lip' => 'Spanish cedar lip',
				'felt'              => 'Felt',
				'magnetic'          => 'Magnetic',
				'gasket'            => 'Gasket',
			),
		),
	);

	/**
	 * Attributes whose presence on an accessory means it contains wood (compliance fields required).
	 */
	public const WOOD_ATTRIBUTES = array( 'exterior-wood', 'interior-lining' );

	/**
	 * Product meta: key => type and group. Specification values are inches; the exterior size and the
	 * shipping weight are WooCommerce's core dimensions and weight. Compliance values are private.
	 *
	 * @var array<string, array{type: string, group: string}>
	 */
	public const META = array(
		'_ch_capacity'            => array(
			'type'  => 'int',
			'group' => 'spec',
		),
		'_ch_max_cigar_length_in' => array(
			'type'  => 'float',
			'group' => 'spec',
		),
		'_ch_max_ring_gauge'      => array(
			'type'  => 'int',
			'group' => 'spec',
		),
		'_ch_interior_length_in'  => array(
			'type'  => 'float',
			'group' => 'spec',
		),
		'_ch_interior_width_in'   => array(
			'type'  => 'float',
			'group' => 'spec',
		),
		'_ch_interior_height_in'  => array(
			'type'  => 'float',
			'group' => 'spec',
		),
		'_ch_wood_species'        => array(
			'type'  => 'textarea',
			'group' => 'compliance',
		),
		'_ch_harvest_country'     => array(
			'type'  => 'text',
			'group' => 'compliance',
		),
		'_ch_composite_wood'      => array(
			'type'  => 'yesno',
			'group' => 'compliance',
		),
		'_ch_hts_code'            => array(
			'type'  => 'hts',
			'group' => 'compliance',
		),
		'_ch_prop65'              => array(
			'type'  => 'yesno',
			'group' => 'compliance',
		),
	);

	/**
	 * Feature ID.
	 *
	 * @return string
	 */
	public static function id(): string {
		return 'CAT-03';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// WooCommerce registers the product post type on init 5; the taxonomy needs it.
		add_action( 'init', array( $this, 'register_taxonomy' ), 6 );
		add_action( 'init', array( $this, 'maybe_ensure' ), 20 );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_data' ) );
		// Price prints at 10 and the summary at 20, in classic and in block templates
		// (SingleProductTemplateCompatibility injects this hook before the summary block).
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_prop65_warning' ), 15 );
	}

	/**
	 * Registers the `occasion` taxonomy (public, REST, /occasions/<slug>/).
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( 'product' ),
			array(
				'labels'             => array(
					'name'          => _x( 'Occasions', 'taxonomy general name', 'cigarholic' ),
					'singular_name' => _x( 'Occasion', 'taxonomy singular name', 'cigarholic' ),
					'search_items'  => __( 'Search occasions', 'cigarholic' ),
					'all_items'     => __( 'All occasions', 'cigarholic' ),
					'edit_item'     => __( 'Edit occasion', 'cigarholic' ),
					'update_item'   => __( 'Update occasion', 'cigarholic' ),
					'add_new_item'  => __( 'Add new occasion', 'cigarholic' ),
					'new_item_name' => __( 'New occasion name', 'cigarholic' ),
					'not_found'     => __( 'No occasions found', 'cigarholic' ),
					'menu_name'     => __( 'Occasions', 'cigarholic' ),
					'back_to_items' => __( 'Back to occasions', 'cigarholic' ),
				),
				'description'        => __( 'Gift occasions used for merchandising (Father\'s Day, Groomsmen, Corporate, ...).', 'cigarholic' ),
				'public'             => true,
				'publicly_queryable' => true,
				'hierarchical'       => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'show_tagcloud'      => false,
				'show_in_quick_edit' => true,
				'query_var'          => true,
				'rewrite'            => array(
					'slug'         => 'occasions',
					'with_front'   => false,
					'hierarchical' => false,
				),
				'capabilities'       => array(
					'manage_terms' => 'manage_product_terms',
					'edit_terms'   => 'edit_product_terms',
					'delete_terms' => 'delete_product_terms',
					'assign_terms' => 'assign_product_terms',
				),
			)
		);
	}

	/**
	 * Ensures the model once per fingerprint (definition + VERSION), so a storefront request costs one
	 * option read.
	 *
	 * @return void
	 */
	public function maybe_ensure(): void {
		if ( ! function_exists( 'wc_create_attribute' ) ) {
			return;
		}
		if ( get_option( self::OPTION ) === self::fingerprint() ) {
			return;
		}
		self::ensure();
	}

	/**
	 * Fingerprint of the model definition.
	 *
	 * @return string
	 */
	public static function fingerprint(): string {
		return md5( self::VERSION . (string) wp_json_encode( array( self::CATEGORIES, self::OCCASIONS, self::ATTRIBUTES ) ) );
	}

	/**
	 * Idempotently creates every category, occasion, global attribute and attribute term that is missing.
	 *
	 * Safe to run on every deploy and in tests. Existing terms are never renamed or deleted.
	 *
	 * @return list<string> Human-readable descriptions of what was created.
	 */
	public static function ensure(): array {
		$changes = array();

		$position = 0;
		foreach ( self::CATEGORIES as $slug => $name ) {
			$term_id = self::ensure_term( 'product_cat', $slug, $name, $changes );
			if ( $term_id > 0 ) {
				self::ensure_order( $term_id, $position );
			}
			++$position;
		}

		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			( new self() )->register_taxonomy();
		}
		foreach ( self::OCCASIONS as $slug => $name ) {
			self::ensure_term( self::TAXONOMY, $slug, $name, $changes );
		}

		foreach ( self::ATTRIBUTES as $slug => $definition ) {
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			if ( 0 === wc_attribute_taxonomy_id_by_name( $slug ) ) {
				$created = wc_create_attribute(
					array(
						'name'         => $definition['label'],
						'slug'         => $slug,
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					)
				);
				if ( is_wp_error( $created ) ) {
					$changes[] = sprintf( 'attribute %s: %s', $slug, $created->get_error_message() );
					continue;
				}
				$changes[] = sprintf( 'attribute %s', $slug );
			}
			// WooCommerce registers pa_* taxonomies on init; a brand-new one needs registering now so
			// its terms can be created in the same request (the CSV importer does the same).
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'labels'       => array( 'name' => $definition['label'] ),
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					)
				);
				self::register_attribute_global( $slug, $taxonomy );
			}
			$position = 0;
			foreach ( $definition['terms'] as $term_slug => $term_name ) {
				$term_id = self::ensure_term( $taxonomy, $term_slug, $term_name, $changes );
				if ( $term_id > 0 ) {
					self::ensure_order( $term_id, $position );
				}
				++$position;
			}
		}

		if ( get_option( self::OPTION ) !== self::fingerprint() ) {
			update_option( self::OPTION, self::fingerprint(), true );
			if ( did_action( 'wp_loaded' ) > 0 ) {
				flush_rewrite_rules( false );
			} else {
				add_action(
					'wp_loaded',
					static function (): void {
						flush_rewrite_rules( false );
					}
				);
			}
		}

		return $changes;
	}

	/**
	 * Adds a just-created attribute to WooCommerce's `$wc_product_attributes` global, which
	 * taxonomy_is_product_attribute() and wc_attribute_label() read; WooCommerce fills it on init from
	 * the attributes that existed at the start of the request.
	 *
	 * @param string $slug     Attribute slug.
	 * @param string $taxonomy Taxonomy name (pa_*).
	 * @return void
	 */
	private static function register_attribute_global( string $slug, string $taxonomy ): void {
		global $wc_product_attributes;
		if ( ! is_array( $wc_product_attributes ) ) {
			$wc_product_attributes = array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's global.
		}
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			if ( isset( $attribute->attribute_name ) && $attribute->attribute_name === $slug ) {
				$wc_product_attributes[ $taxonomy ] = $attribute; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's global.
				return;
			}
		}
	}

	/**
	 * Term ID for a slug in a taxonomy, created when missing.
	 *
	 * @param string             $taxonomy Taxonomy.
	 * @param string             $slug     Term slug.
	 * @param string             $name     Term name used on creation.
	 * @param array<int, string> $changes  Change log, appended to.
	 * @return int 0 on failure.
	 */
	public static function ensure_term( string $taxonomy, string $slug, string $name, array &$changes ): int {
		$existing = get_term_by( 'slug', $slug, $taxonomy );
		if ( $existing instanceof WP_Term ) {
			return (int) $existing->term_id;
		}
		$inserted = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $inserted ) ) {
			$changes[] = sprintf( 'term %s/%s: %s', $taxonomy, $slug, $inserted->get_error_message() );
			return 0;
		}
		$changes[] = sprintf( 'term %s/%s', $taxonomy, $slug );
		return (int) $inserted['term_id'];
	}

	/**
	 * Term ID for a slug, or 0. Read-only companion of ensure_term() for the seed and the importer.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Term slug.
	 * @return int
	 */
	public static function term_id( string $taxonomy, string $slug ): int {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		return $term instanceof WP_Term ? (int) $term->term_id : 0;
	}

	/**
	 * Sets the WooCommerce term order when it differs.
	 *
	 * @param int $term_id  Term ID.
	 * @param int $position Position.
	 * @return void
	 */
	private static function ensure_order( int $term_id, int $position ): void {
		$current = (string) get_term_meta( $term_id, 'order', true );
		if ( (string) $position !== $current ) {
			update_term_meta( $term_id, 'order', $position );
		}
	}

	/**
	 * Adds the "Specifications" tab to the product data box.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function add_product_data_tab( array $tabs ): array {
		$tabs['cigarholic_specs'] = array(
			'label'    => __( 'Specifications', 'cigarholic' ),
			'target'   => 'cigarholic_specs_data',
			'class'    => array(),
			'priority' => 25,
		);
		return $tabs;
	}

	/**
	 * Labels and help texts of the meta fields, in display order.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function meta_fields(): array {
		return array(
			'_ch_capacity'            => array(
				'label'       => __( 'Cigar capacity', 'cigarholic' ),
				'description' => __( 'Number of 50 ring gauge cigars the humidor holds. Required for every humidor.', 'cigarholic' ),
			),
			'_ch_max_cigar_length_in' => array(
				'label'       => __( 'Max cigar length (in)', 'cigarholic' ),
				'description' => __( 'Longest cigar that fits, in inches. Shown as inches and centimetres.', 'cigarholic' ),
			),
			'_ch_max_ring_gauge'      => array(
				'label'       => __( 'Max ring gauge', 'cigarholic' ),
				'description' => __( 'Largest ring gauge that fits in a slot or tray.', 'cigarholic' ),
			),
			'_ch_interior_length_in'  => array(
				'label'       => __( 'Interior length (in)', 'cigarholic' ),
				'description' => __( 'Inside measurement in inches. The exterior size and the shipping weight are the Shipping tab fields.', 'cigarholic' ),
			),
			'_ch_interior_width_in'   => array(
				'label'       => __( 'Interior width (in)', 'cigarholic' ),
				'description' => __( 'Inside measurement in inches.', 'cigarholic' ),
			),
			'_ch_interior_height_in'  => array(
				'label'       => __( 'Interior height (in)', 'cigarholic' ),
				'description' => __( 'Inside measurement in inches.', 'cigarholic' ),
			),
			'_ch_wood_species'        => array(
				'label'       => __( 'Wood species (private)', 'cigarholic' ),
				'description' => __( 'One line per wood including lining and veneer: "Part: Genus species, Country", for example "Exterior: Juglans nigra, United States". Lacey Act declaration data; never shown to customers.', 'cigarholic' ),
			),
			'_ch_harvest_country'     => array(
				'label'       => __( 'Country of harvest (private)', 'cigarholic' ),
				'description' => __( 'Where the wood was harvested (not where the piece was made). Separate several countries with a semicolon.', 'cigarholic' ),
			),
			'_ch_composite_wood'      => array(
				'label'       => __( 'Contains composite wood (private)', 'cigarholic' ),
				'description' => __( 'Plywood, MDF or particleboard anywhere in the piece: TSCA Title VI formaldehyde rules apply.', 'cigarholic' ),
			),
			'_ch_hts_code'            => array(
				'label'       => __( 'HTS code (private)', 'cigarholic' ),
				'description' => __( 'US Harmonized Tariff Schedule code, digits and dots, for example 4420.90.6500. Confirmed by the customs broker.', 'cigarholic' ),
			),
			'_ch_prop65'              => array(
				'label'       => __( 'California Prop 65 warning', 'cigarholic' ),
				'description' => __( 'Off by default (D-08). When on, the standard Prop 65 warning is shown below the price on the product page.', 'cigarholic' ),
			),
		);
	}

	/**
	 * Outputs the "Specifications" panel.
	 *
	 * @return void
	 */
	public function render_product_data_panel(): void {
		global $product_object;
		$product = $product_object instanceof WC_Product ? $product_object : null;
		$fields  = self::meta_fields();

		echo '<div id="cigarholic_specs_data" class="panel woocommerce_options_panel hidden">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		echo '<div class="options_group">';
		echo '<p class="form-field"><em>' . esc_html__( 'Exact values for the spec table, in inches. Filterable properties (wood, lining, humidification, finish, ...) are global attributes on the Attributes tab. Only attributes that change the SKU (finish, size) are used for variations; everything else stays a plain attribute.', 'cigarholic' ) . '</em></p>';
		foreach ( self::META as $key => $definition ) {
			if ( 'spec' !== $definition['group'] ) {
				continue;
			}
			$this->render_field( $key, $definition['type'], $fields[ $key ], $product );
		}
		echo '</div>';

		echo '<div class="options_group">';
		echo '<p class="form-field"><em>' . esc_html__( 'Compliance (private): import paperwork for the customs broker. Never shown on the storefront.', 'cigarholic' ) . '</em></p>';
		foreach ( self::META as $key => $definition ) {
			if ( 'compliance' !== $definition['group'] ) {
				continue;
			}
			$this->render_field( $key, $definition['type'], $fields[ $key ], $product );
		}
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Outputs one field with the WooCommerce helpers.
	 *
	 * @param string                                    $key     Meta key.
	 * @param string                                    $type    Field type.
	 * @param array{label: string, description: string} $labels  Label and description.
	 * @param WC_Product|null                           $product Product being edited.
	 * @return void
	 */
	private function render_field( string $key, string $type, array $labels, ?WC_Product $product ): void {
		$field = array(
			'id'          => $key,
			'label'       => $labels['label'],
			'description' => $labels['description'],
			'desc_tip'    => true,
		);
		switch ( $type ) {
			case 'int':
				$field['type']              = 'number';
				$field['custom_attributes'] = array(
					'min'  => '0',
					'step' => '1',
				);
				woocommerce_wp_text_input( $field, $product );
				break;
			case 'float':
				$field['data_type'] = 'decimal';
				woocommerce_wp_text_input( $field, $product );
				break;
			case 'textarea':
				$field['rows'] = 4;
				woocommerce_wp_textarea_input( $field, $product );
				break;
			case 'yesno':
				$field['cbvalue'] = 'yes';
				woocommerce_wp_checkbox( $field, $product );
				break;
			default:
				woocommerce_wp_text_input( $field, $product );
		}
	}

	/**
	 * Saves the panel fields on the product object (WooCommerce saves the object afterwards).
	 *
	 * @param WC_Product $product Product being saved.
	 * @return void
	 */
	public function save_product_data( WC_Product $product ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_products' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			return;
		}
		foreach ( self::META as $key => $definition ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				$raw = '';
			} elseif ( 'textarea' === $definition['type'] ) {
				$raw = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) );
			} else {
				$raw = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
			$value = self::sanitize_value( $key, $raw );
			if ( '' === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}
	}

	/**
	 * Normalises a submitted meta value: '' means "delete".
	 *
	 * @param string $key Meta key.
	 * @param string $raw Sanitised text.
	 * @return string
	 */
	public static function sanitize_value( string $key, string $raw ): string {
		$type = self::META[ $key ]['type'] ?? 'text';
		$raw  = trim( $raw );
		switch ( $type ) {
			case 'int':
				if ( '' === $raw ) {
					return '';
				}
				$number = absint( (float) wc_format_decimal( $raw ) );
				return $number > 0 ? (string) $number : '';
			case 'float':
				if ( '' === $raw ) {
					return '';
				}
				$number = (float) wc_format_decimal( $raw );
				return $number > 0 ? (string) wc_format_decimal( $number, 3, true ) : '';
			case 'yesno':
				if ( '' === $raw ) {
					return '';
				}
				return self::is_yes( $raw ) ? 'yes' : 'no';
			case 'hts':
				return (string) preg_replace( '/[^0-9.]/', '', $raw );
			default:
				return $raw;
		}
	}

	/**
	 * Whether a stored yes/no value means yes ("yes", "1", "true", "on").
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	public static function is_yes( mixed $value ): bool {
		return is_scalar( $value ) && in_array( strtolower( trim( (string) $value ) ), array( 'yes', '1', 'true', 'on', 'y' ), true );
	}

	/**
	 * Integer meta value of a product, 0 when missing.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $key     Meta key.
	 * @return int
	 */
	public static function meta_int( WC_Product $product, string $key ): int {
		$value = $product->get_meta( $key, true, 'edit' );
		return is_scalar( $value ) ? absint( (float) wc_format_decimal( (string) $value ) ) : 0;
	}

	/**
	 * Float meta value of a product, 0.0 when missing.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $key     Meta key.
	 * @return float
	 */
	public static function meta_float( WC_Product $product, string $key ): float {
		$value = $product->get_meta( $key, true, 'edit' );
		return is_scalar( $value ) ? max( 0.0, (float) wc_format_decimal( (string) $value ) ) : 0.0;
	}

	/**
	 * Trimmed string meta value of a product.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $key     Meta key.
	 * @return string
	 */
	public static function meta_string( WC_Product $product, string $key ): string {
		$value = $product->get_meta( $key, true, 'edit' );
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Prints the Prop 65 warning below the price when the product's toggle is on (CMP-06).
	 *
	 * @return void
	 */
	public function render_prop65_warning(): void {
		global $product;
		if ( ! $product instanceof WC_Product || ! self::is_yes( $product->get_meta( '_ch_prop65', true, 'edit' ) ) ) {
			return;
		}
		wp_register_style( 'cigarholic-prop65', false, array(), '1' );
		wp_enqueue_style( 'cigarholic-prop65' );
		wp_add_inline_style( 'cigarholic-prop65', '.cigarholic-prop65{border-left:4px solid #e4a11b;padding:.5em .75em;font-size:var(--wp--preset--font-size--small,.875rem);margin:0 0 1em}.cigarholic-prop65__symbol{color:#e4a11b}' );
		echo self::prop65_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in prop65_html().
	}

	/**
	 * The standard Prop 65 warning (safe harbor wording, carcinogen). The chemical defaults to wood dust;
	 * `cigarholic_prop65_chemicals` lets the owner's determination (L-09) name others.
	 *
	 * @return string Escaped HTML.
	 */
	public static function prop65_html(): string {
		/**
		 * Filters the chemical(s) named in the Prop 65 warning.
		 *
		 * @param string $chemicals Comma-separated chemical names.
		 */
		$chemicals = (string) apply_filters( 'cigarholic_prop65_chemicals', __( 'wood dust', 'cigarholic' ) );
		$link      = '<a href="https://www.p65warnings.ca.gov" rel="noopener">www.P65Warnings.ca.gov</a>';

		return '<p class="cigarholic-prop65" role="note"><span class="cigarholic-prop65__symbol" aria-hidden="true">&#9888;</span> <strong>'
			. esc_html__( 'WARNING:', 'cigarholic' ) . '</strong> '
			. sprintf(
				/* translators: 1: chemical name(s), 2: link to www.P65Warnings.ca.gov */
				esc_html__( 'This product can expose you to chemicals including %1$s, which is known to the State of California to cause cancer. For more information go to %2$s.', 'cigarholic' ),
				esc_html( $chemicals ),
				$link
			)
			. '</p>';
	}
}
