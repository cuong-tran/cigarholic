<?php
/**
 * Colour contrast of the brand tokens (theme.json and the section styles) and registration of the Forest style.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_Theme_JSON_Resolver;
use WP_UnitTestCase;

/**
 * Feature IDs: CNT-01 (brand system), CMP-04 (contrast), ORD-03 (email colours).
 *
 * Every text colour the theme declares is checked against the background it is declared on or
 * inherits, at WCAG 2.2 AA for normal text (4.5:1): the root, its elements (links and their
 * states, headings, captions, buttons and their states), every block style and block style
 * variation, and every partial under styles/ (the Forest section style). A Cedar link or button
 * added to the Forest section (2.52:1) therefore fails here, before any page is rendered.
 */
final class ThemeContrastTest extends WP_UnitTestCase {

	/**
	 * WCAG 2.2 AA minimum for normal-size text.
	 */
	private const AA = 4.5;

	/**
	 * Blocks whose `color.text` paints a rule, not text (decoration has no contrast minimum).
	 */
	private const DECORATIVE_TEXT_COLOR = array( 'core/separator' );

	/**
	 * Palette slug => lowercase hex, from theme.json.
	 *
	 * @var array<string, string>
	 */
	private array $palette = array();

	/**
	 * Pairs below the minimum, as readable lines.
	 *
	 * @var list<string>
	 */
	private array $failures = array();

	/**
	 * Number of pairs checked.
	 *
	 * @var int
	 */
	private int $checked = 0;

	/**
	 * Absolute path of the theme in this repository.
	 *
	 * @return string
	 */
	private static function theme_dir(): string {
		return dirname( __DIR__, 2 ) . '/web/app/themes/cigarholic';
	}

	/**
	 * Decodes a JSON file into an array.
	 *
	 * @param string $path File path.
	 * @return array<string, mixed>
	 */
	private static function read_json( string $path ): array {
		$data = wp_json_file_decode( $path, array( 'associative' => true ) );
		self::assertIsArray( $data, $path . ' is not valid JSON' );
		return $data;
	}

	/**
	 * Loads the palette.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$theme = self::read_json( self::theme_dir() . '/theme.json' );
		foreach ( $theme['settings']['color']['palette'] as $entry ) {
			$this->palette[ $entry['slug'] ] = strtolower( $entry['color'] );
		}
		$this->failures = array();
		$this->checked  = 0;
	}

	/**
	 * Relative luminance of a #rrggbb colour (WCAG 2.2 definition).
	 *
	 * @param string $hex Colour.
	 * @return float
	 */
	private static function luminance( string $hex ): float {
		$channels = array_map(
			static function ( string $pair ): float {
				$c = hexdec( $pair ) / 255;
				return $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			},
			str_split( substr( $hex, 1 ), 2 )
		);
		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * Contrast ratio between two #rrggbb colours.
	 *
	 * @param string $a First colour.
	 * @param string $b Second colour.
	 * @return float
	 */
	private static function ratio( string $a, string $b ): float {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );
		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Resolves a theme.json colour value to hex; null when it sets nothing (absent or transparent).
	 *
	 * @param mixed $value Value from theme.json.
	 * @return string|null
	 */
	private function resolve( $value ): ?string {
		if ( null === $value || 'transparent' === $value ) {
			return null;
		}
		$this->assertIsString( $value );
		if ( preg_match( '/^var:preset\|color\|([a-z0-9-]+)$/', $value, $m ) || preg_match( '/^var\(--wp--preset--color--([a-z0-9-]+)\)$/', $value, $m ) ) {
			$this->assertArrayHasKey( $m[1], $this->palette, 'Unknown palette slug ' . $m[1] );
			return $this->palette[ $m[1] ];
		}
		$this->assertMatchesRegularExpression( '/^#[0-9a-f]{6}$/i', $value, 'Colour the test cannot resolve: ' . $value );
		return strtolower( $value );
	}

	/**
	 * Records one text/background pair.
	 *
	 * @param string $where      Readable location.
	 * @param string $text       Text colour.
	 * @param string $background Background colour.
	 * @return void
	 */
	private function pair( string $where, string $text, string $background ): void {
		++$this->checked;
		$ratio = self::ratio( $text, $background );
		if ( $ratio < self::AA ) {
			$this->failures[] = sprintf( '%s: %s on %s is %.2f:1', $where, $text, $background, $ratio );
		}
	}

	/**
	 * Checks one style context and everything nested in it.
	 *
	 * @param string               $where      Readable location.
	 * @param array<string, mixed> $styles     The context's styles.
	 * @param string               $text       Inherited text colour.
	 * @param string               $background Inherited background colour.
	 * @param bool                 $decorative Whether color.text paints decoration here.
	 * @return void
	 */
	private function walk( string $where, array $styles, string $text, string $background, bool $decorative = false ): void {
		$background = $this->resolve( $styles['color']['background'] ?? null ) ?? $background;
		$text       = $this->resolve( $styles['color']['text'] ?? null ) ?? $text;
		if ( ! $decorative ) {
			$this->pair( $where, $text, $background );
		}

		foreach ( $styles['elements'] ?? array() as $element => $element_styles ) {
			$element_text       = $this->resolve( $element_styles['color']['text'] ?? null ) ?? $text;
			$element_background = $this->resolve( $element_styles['color']['background'] ?? null ) ?? $background;
			$this->pair( $where . ' > ' . $element, $element_text, $element_background );
			foreach ( array( ':hover', ':focus', ':active' ) as $state ) {
				if ( isset( $element_styles[ $state ] ) ) {
					$this->pair(
						$where . ' > ' . $element . $state,
						$this->resolve( $element_styles[ $state ]['color']['text'] ?? null ) ?? $element_text,
						$this->resolve( $element_styles[ $state ]['color']['background'] ?? null ) ?? $element_background
					);
				}
			}
		}

		foreach ( $styles['blocks'] ?? array() as $block => $block_styles ) {
			$is_decorative = in_array( $block, self::DECORATIVE_TEXT_COLOR, true );
			$this->walk( $where . ' > ' . $block, $block_styles, $text, $background, $is_decorative );
			$block_background = $this->resolve( $block_styles['color']['background'] ?? null ) ?? $background;
			$block_text       = $this->resolve( $block_styles['color']['text'] ?? null ) ?? $text;
			foreach ( $block_styles['variations'] ?? array() as $variation => $variation_styles ) {
				$this->walk( $where . ' > ' . $block . '.is-style-' . $variation, $variation_styles, $block_text, $block_background, $is_decorative );
			}
		}
	}

	/**
	 * Every text colour in theme.json and in every styles/ partial passes AA on its background.
	 */
	public function test_every_declared_text_colour_passes_aa(): void {
		$theme     = self::read_json( self::theme_dir() . '/theme.json' );
		$root_bg   = $this->resolve( $theme['styles']['color']['background'] );
		$root_text = $this->resolve( $theme['styles']['color']['text'] );
		$this->assertNotNull( $root_bg );
		$this->assertNotNull( $root_text );
		$this->walk( 'theme.json', $theme['styles'], $root_text, $root_bg );

		$partials = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( self::theme_dir() . '/styles', RecursiveDirectoryIterator::SKIP_DOTS ) );
		$count    = 0;
		foreach ( $partials as $file ) {
			if ( 'json' !== $file->getExtension() ) {
				continue;
			}
			++$count;
			$partial = self::read_json( $file->getPathname() );
			$this->walk( 'styles/' . substr( $file->getPathname(), strlen( self::theme_dir() . '/styles/' ) ), $partial['styles'] ?? array(), $root_text, $root_bg );
		}

		$this->assertGreaterThanOrEqual( 1, $count, 'No section style found under styles/' );
		$this->assertGreaterThan( 30, $this->checked, 'Suspiciously few pairs checked' );
		$this->assertSame( array(), $this->failures, implode( "\n", $this->failures ) );
	}

	/**
	 * The brand rules as a matrix: every text colour allowed on a ground passes AA on it.
	 *
	 * Patterns and the manager pick backgrounds by slug in block markup, which theme.json does not
	 * see, so the rule is checked for each ground the theme offers: on light grounds (Ash, Ash tint)
	 * Ink, Stone and Cedar; on Forest and Forest raised Ash, Mist and Gold. Gold on light and Cedar
	 * on Forest are excluded on purpose (2.36:1 and 2.52:1). A new background colour has to be added
	 * here, which forces the question.
	 */
	public function test_palette_rules_pass_aa(): void {
		$rules = array(
			'base'      => array( 'contrast', 'neutral-2', 'accent' ),
			'neutral-1' => array( 'contrast', 'neutral-2', 'accent' ),
			'forest'    => array( 'base', 'mist', 'gold' ),
			'forest-2'  => array( 'base', 'mist', 'gold' ),
		);
		foreach ( $rules as $ground => $texts ) {
			foreach ( $texts as $text ) {
				$this->pair( $text . ' on ' . $ground, $this->palette[ $text ], $this->palette[ $ground ] );
			}
		}
		$this->assertSame( array(), $this->failures, implode( "\n", $this->failures ) );

		$grounds_and_rules = array_merge( array_keys( $rules ), array( 'contrast', 'neutral-2', 'accent', 'mist', 'gold', 'line' ) );
		$this->assertSame( array(), array_values( array_diff( array_keys( $this->palette ), $grounds_and_rules ) ), 'A palette colour has no contrast rule yet' );
	}

	/**
	 * The checker itself catches the pairing the brand rules forbid.
	 */
	public function test_cedar_on_forest_is_caught(): void {
		$this->walk(
			'probe',
			array(
				'color'    => array( 'background' => 'var:preset|color|forest' ),
				'elements' => array( 'link' => array( 'color' => array( 'text' => 'var:preset|color|accent' ) ) ),
			),
			$this->palette['base'],
			$this->palette['base']
		);
		$this->assertCount( 1, $this->failures );
		$this->assertStringContainsString( 'probe > link', $this->failures[0] );
		$this->assertStringContainsString( '2.52:1', $this->failures[0] );
	}

	/**
	 * The transactional email colours set by provisioning step 70 pass AA.
	 */
	public function test_email_colours_pass_aa(): void {
		// Body text on the body, footer text on the outer background, links and headings (base colour)
		// on the body, and the header text WooCommerce picks for a dark base colour (white) on Forest.
		$this->pair( 'email body text', $this->palette['contrast'], $this->palette['neutral-1'] );
		$this->pair( 'email footer text', $this->palette['neutral-2'], $this->palette['base'] );
		$this->pair( 'email links', $this->palette['forest'], $this->palette['neutral-1'] );
		$this->pair( 'email header text', '#ffffff', $this->palette['forest'] );
		$this->assertSame( array(), $this->failures, implode( "\n", $this->failures ) );
	}

	/**
	 * WordPress discovers styles/sections/forest.json and registers it for groups and columns.
	 */
	public function test_forest_section_style_is_registered(): void {
		$this->assertSame( 'cigarholic', get_stylesheet() );

		$forest = null;
		foreach ( WP_Theme_JSON_Resolver::get_style_variations( 'block' ) as $variation ) {
			if ( 'forest' === ( $variation['slug'] ?? '' ) ) {
				$forest = $variation;
			}
		}
		$this->assertIsArray( $forest, 'styles/sections/forest.json was not discovered' );
		$this->assertSame( array( 'core/group', 'core/columns' ), $forest['blockTypes'] );

		WP_Theme_JSON_Resolver::get_theme_data();
		$registry = \WP_Block_Styles_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'core/group', 'forest' ) );
		$this->assertTrue( $registry->is_registered( 'core/columns', 'forest' ) );
	}
}
