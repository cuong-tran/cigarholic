<?php
/**
 * Provisioning step context.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Core\Provision;

/**
 * Helpers a provisioning step uses to apply settings idempotently and report what changed.
 * Every helper compares before writing, so re-running a step is a no-op.
 *
 * Feature ID: FND-03.
 */
final class Context {

	/**
	 * Number of changes applied (or that would be applied in a dry run).
	 *
	 * @var int
	 */
	private int $changes = 0;

	/**
	 * Constructor.
	 *
	 * @param bool                   $dry_run Report without changing.
	 * @param callable(string): void $output  Receives log lines.
	 */
	public function __construct( private readonly bool $dry_run, private $output ) {}

	/**
	 * Whether this is a dry run.
	 *
	 * @return bool
	 */
	public function is_dry_run(): bool {
		return $this->dry_run;
	}

	/**
	 * Changes counted so far.
	 *
	 * @return int
	 */
	public function changes(): int {
		return $this->changes;
	}

	/**
	 * Logs a line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public function log( string $message ): void {
		( $this->output )( sprintf( '   %s', $message ) );
	}

	/**
	 * Records a change made by custom code in a step (after the step checked that it is needed).
	 *
	 * @param string $description What changed.
	 * @return void
	 */
	public function changed( string $description ): void {
		++$this->changes;
		$this->log( ( $this->dry_run ? 'would change: ' : 'changed: ' ) . $description );
	}

	/**
	 * Sets an option when its value differs.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Wanted value.
	 * @return bool Whether a change was needed.
	 */
	public function option( string $name, mixed $value ): bool {
		$current = get_option( $name, null );
		if ( $this->same( $current, $value ) ) {
			$this->log( sprintf( 'ok: %s', $name ) );
			return false;
		}
		if ( ! $this->dry_run ) {
			update_option( $name, $value );
		}
		$this->changed( sprintf( 'option %s', $name ) );
		return true;
	}

	/**
	 * Ensures a page with this slug exists and returns its ID.
	 *
	 * Creates the page when no page with the slug exists (trash excluded); an existing page is left
	 * untouched, whatever its title, content or status, because page content belongs to the manager
	 * once it exists. Steps that need a page's ID for an option use the return value.
	 *
	 * @param string $slug    Page slug (post_name), kept verbatim even for drafts.
	 * @param string $title   Title used when creating.
	 * @param string $content Block content used when creating.
	 * @param string $status  Status used when creating: publish or draft.
	 * @return int Page ID; 0 in a dry run when the page would be created.
	 */
	public function page( string $slug, string $title, string $content, string $status = 'publish' ): int {
		$existing = $this->find_post( 'page', $slug );
		if ( null !== $existing ) {
			$this->log( sprintf( 'ok: page %s (#%d, %s)', $slug, $existing->ID, $existing->post_status ) );
			return $existing->ID;
		}

		$this->changed( sprintf( 'page %s (%s)', $slug, $status ) );
		if ( $this->dry_run ) {
			return 0;
		}

		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_name'      => $slug,
				'post_title'     => $title,
				'post_content'   => $content,
				'post_status'    => $status,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( sprintf( 'Could not create page %s: %s', esc_html( $slug ), esc_html( $id->get_error_message() ) ) );
		}
		return (int) $id;
	}

	/**
	 * Ensures a term exists with this name (and description, when given) and returns its ID.
	 *
	 * The term is looked up by slug; it is created when missing and its name and description are
	 * updated when they differ.
	 *
	 * @param string               $taxonomy Taxonomy, for example product_shipping_class.
	 * @param string               $slug     Term slug.
	 * @param string               $name     Term name.
	 * @param array<string, mixed> $args     Further wp_insert_term() / wp_update_term() arguments
	 *                                       (description, parent). Only description is compared.
	 * @return int Term ID; 0 in a dry run when the term would be created.
	 */
	public function term( string $taxonomy, string $slug, string $name, array $args = array() ): int {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term instanceof \WP_Term ) {
			$wanted_description = isset( $args['description'] ) ? (string) $args['description'] : $term->description;
			if ( $term->name === $name && $term->description === $wanted_description ) {
				$this->log( sprintf( 'ok: term %s/%s', $taxonomy, $slug ) );
				return $term->term_id;
			}
			if ( ! $this->dry_run ) {
				$result = wp_update_term( $term->term_id, $taxonomy, array_merge( $args, array( 'name' => $name ) ) );
				if ( is_wp_error( $result ) ) {
					throw new \RuntimeException( sprintf( 'Could not update term %s/%s: %s', esc_html( $taxonomy ), esc_html( $slug ), esc_html( $result->get_error_message() ) ) );
				}
			}
			$this->changed( sprintf( 'term %s/%s', $taxonomy, $slug ) );
			return $term->term_id;
		}

		$this->changed( sprintf( 'term %s/%s (new)', $taxonomy, $slug ) );
		if ( $this->dry_run ) {
			return 0;
		}
		$result = wp_insert_term( $name, $taxonomy, array_merge( $args, array( 'slug' => $slug ) ) );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( sprintf( 'Could not create term %s/%s: %s', esc_html( $taxonomy ), esc_html( $slug ), esc_html( $result->get_error_message() ) ) );
		}
		return (int) $result['term_id'];
	}

	/**
	 * Ensures a block navigation menu (wp_navigation post) exists with exactly these top-level links.
	 *
	 * The menu is looked up by slug, created when missing and updated in place when its title or
	 * links differ; it is always published, so the Navigation block falls back to it when no `ref`
	 * is set (WordPress picks the most recently published wp_navigation post).
	 *
	 * @param string                                  $title Menu title.
	 * @param string                                  $slug  Menu slug (post_name).
	 * @param list<array{label: string, url: string}> $items Links in order.
	 * @return int Post ID; 0 in a dry run when the menu would be created.
	 */
	public function navigation( string $title, string $slug, array $items ): int {
		$blocks = array();
		foreach ( $items as $item ) {
			$blocks[] = array(
				'blockName'    => 'core/navigation-link',
				'attrs'        => array(
					'label'          => $item['label'],
					'url'            => $item['url'],
					'kind'           => 'custom',
					'isTopLevelLink' => true,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			);
		}
		$content  = serialize_blocks( $blocks );
		$existing = $this->find_post( 'wp_navigation', $slug );

		if ( null !== $existing ) {
			if ( $existing->post_title === $title && $existing->post_content === $content && 'publish' === $existing->post_status ) {
				$this->log( sprintf( 'ok: navigation %s (#%d)', $slug, $existing->ID ) );
				return $existing->ID;
			}
			if ( ! $this->dry_run ) {
				$result = wp_update_post(
					array(
						'ID'           => $existing->ID,
						'post_title'   => $title,
						'post_content' => $content,
						'post_status'  => 'publish',
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					throw new \RuntimeException( sprintf( 'Could not update navigation %s: %s', esc_html( $slug ), esc_html( $result->get_error_message() ) ) );
				}
			}
			$this->changed( sprintf( 'navigation %s', $slug ) );
			return $existing->ID;
		}

		$this->changed( sprintf( 'navigation %s (new)', $slug ) );
		if ( $this->dry_run ) {
			return 0;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( sprintf( 'Could not create navigation %s: %s', esc_html( $slug ), esc_html( $id->get_error_message() ) ) );
		}
		return (int) $id;
	}

	/**
	 * Finds a post of a type by slug, in any status except trash and auto-draft.
	 *
	 * @param string $post_type Post type.
	 * @param string $slug      Slug (post_name).
	 * @return \WP_Post|null
	 */
	private function find_post( string $post_type, string $slug ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'name'             => $slug,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'   => 1,
				'suppress_filters' => false,
				'no_found_rows'    => true,
			)
		);
		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				return $post;
			}
		}
		return null;
	}

	/**
	 * Loose comparison that treats "yes"/"no", numeric strings and arrays sensibly.
	 *
	 * @param mixed $current Current value.
	 * @param mixed $wanted  Wanted value.
	 * @return bool
	 */
	private function same( mixed $current, mixed $wanted ): bool {
		if ( is_array( $wanted ) || is_array( $current ) ) {
			return $current === $wanted;
		}
		if ( null === $current ) {
			return false;
		}
		return (string) $current === (string) $wanted;
	}
}
