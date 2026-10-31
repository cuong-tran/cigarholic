<?php
/**
 * Provisioning step: roles (ORD-04).
 *
 * The store manager uses WooCommerce's Shop Manager role: products, orders, coupons, reports, and
 * pages and posts for content. Nothing that changes code or the site's design: no plugins, themes,
 * theme options (site editor, menus), core updates or file editing. WooCommerce grants
 * edit_theme_options to the role by default; it is taken away here, menus are provisioned.
 * Administrators keep every WooCommerce capability.
 *
 * Feature IDs: FND-03, ORD-04.
 *
 * @package Cigarholic
 */

use Cigarholic\Core\Provision\Context;

return static function ( Context $ctx ): void {
	/**
	 * Grants or revokes capabilities on a role, idempotently.
	 *
	 * @param string       $role_name Role.
	 * @param list<string> $grant     Capabilities the role must have.
	 * @param list<string> $revoke    Capabilities the role must not have.
	 * @return void
	 */
	$ensure = static function ( string $role_name, array $grant, array $revoke ) use ( $ctx ): void {
		$role = get_role( $role_name );
		if ( null === $role ) {
			$ctx->log( sprintf( 'skip: role %s does not exist (WooCommerce creates it on install)', $role_name ) );
			return;
		}
		$changes = 0;
		foreach ( $grant as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				if ( ! $ctx->is_dry_run() ) {
					$role->add_cap( $cap );
				}
				$ctx->changed( sprintf( '%s +%s', $role_name, $cap ) );
				++$changes;
			}
		}
		foreach ( $revoke as $cap ) {
			if ( $role->has_cap( $cap ) ) {
				if ( ! $ctx->is_dry_run() ) {
					$role->remove_cap( $cap );
				}
				$ctx->changed( sprintf( '%s -%s', $role_name, $cap ) );
				++$changes;
			}
		}
		if ( 0 === $changes ) {
			$ctx->log( sprintf( 'ok: role %s', $role_name ) );
		}
	};

	$woocommerce = array( 'manage_woocommerce', 'view_woocommerce_reports', 'create_customers' );
	foreach ( array( 'product', 'shop_order', 'shop_coupon' ) as $type ) {
		foreach ( array( 'edit_%s', 'read_%s', 'delete_%s', 'edit_%ss', 'edit_others_%ss', 'publish_%ss', 'read_private_%ss', 'delete_%ss', 'delete_private_%ss', 'delete_published_%ss', 'delete_others_%ss', 'edit_private_%ss', 'edit_published_%ss', 'manage_%s_terms', 'edit_%s_terms', 'delete_%s_terms', 'assign_%s_terms' ) as $pattern ) {
			$woocommerce[] = sprintf( $pattern, $type );
		}
	}

	$content = array(
		'read',
		'upload_files',
		'edit_posts',
		'edit_others_posts',
		'edit_published_posts',
		'edit_private_posts',
		'publish_posts',
		'delete_posts',
		'delete_others_posts',
		'delete_published_posts',
		'delete_private_posts',
		'read_private_posts',
		'edit_pages',
		'edit_others_pages',
		'edit_published_pages',
		'edit_private_pages',
		'publish_pages',
		'delete_pages',
		'delete_others_pages',
		'delete_published_pages',
		'delete_private_pages',
		'read_private_pages',
		'manage_categories',
		'moderate_comments',
		'list_users',
		'export',
		'import',
	);

	// Code, design and site configuration stay with administrators (ORD-04). `import` stays: the
	// product CSV importer (CAT-10) needs it together with edit_products.
	$forbidden = array(
		'install_plugins',
		'activate_plugins',
		'update_plugins',
		'delete_plugins',
		'edit_plugins',
		'install_themes',
		'update_themes',
		'delete_themes',
		'edit_themes',
		'switch_themes',
		'edit_theme_options',
		'update_core',
		'edit_files',
		'manage_options',
	);

	$ensure( 'shop_manager', array_merge( $woocommerce, $content ), $forbidden );
	$ensure( 'administrator', $woocommerce, array() );
};
