<?php
/**
 * Sponsor post type, Team taxonomy, link field, block binding source and Query Loop team filter.
 *
 * Logo = featured image. Website = the hopfly_sponsor_url field. Order = the "Order" attribute (title sponsors first).
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

add_action( 'init', __NAMESPACE__ . '\register_post_types' );

/**
 * Register Sponsor, Event, and their taxonomies and meta.
 */
function register_post_types(): void {
	register_post_type(
		'hopfly_sponsor',
		array(
			'labels'       => array(
				'name'          => __( 'Sponsors', 'hopfly' ),
				'singular_name' => __( 'Sponsor', 'hopfly' ),
				'add_new_item'  => __( 'Add new sponsor', 'hopfly' ),
				'edit_item'     => __( 'Edit sponsor', 'hopfly' ),
				'menu_name'     => __( 'Sponsors', 'hopfly' ),
			),
			// The Query Loop block only lists "viewable" post types, so Sponsors must be publicly queryable.
			// There is no rewrite or query var, so no sponsor URL exists; direct hits 404 (see below).
			'public'             => false,
			'publicly_queryable' => true,
			'query_var'          => false,
			'exclude_from_search' => true,
			'show_in_nav_menus'  => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-heart',
			'supports'     => array( 'title', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'has_archive'  => false,
			'rewrite'      => false,
		)
	);

	register_taxonomy(
		'hopfly_team',
		'hopfly_sponsor',
		array(
			'labels'            => array(
				'name'          => __( 'Teams', 'hopfly' ),
				'singular_name' => __( 'Team', 'hopfly' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		)
	);

	register_post_meta(
		'hopfly_sponsor',
		'hopfly_sponsor_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
		)
	);

	register_event_types();
}

/**
 * Teams that the sponsor strip can filter on.
 */
function default_teams(): array {
	return array(
		'club'           => 'Club',
		'racing'         => 'Racing',
		'juniors'        => 'Juniors',
		'cross-and-brew' => 'Cross & Brew',
		'tri-and-brew'   => 'Tri & Brew',
		'jianna'         => 'Jianna',
	);
}

/**
 * Block binding source for the sponsor strip: url, label, target, rel.
 */
add_action(
	'init',
	static function () {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			'hopfly/sponsor',
			array(
				'label'              => __( 'HopFly sponsor', 'hopfly' ),
				'uses_context'       => array( 'postId', 'postType' ),
				'get_value_callback' => static function ( array $args, $block ) {
					$post_id = $block->context['postId'] ?? 0;
					if ( ! $post_id || 'hopfly_sponsor' !== get_post_type( $post_id ) ) {
						return null;
					}
					$url = (string) get_post_meta( $post_id, 'hopfly_sponsor_url', true );
					switch ( $args['key'] ?? '' ) {
						case 'url':
							return $url ? $url : null;
						case 'label':
							/* translators: %s: sponsor name. */
							return sprintf( __( 'Visit %s (opens in a new tab)', 'hopfly' ), get_the_title( $post_id ) );
						case 'target':
							return $url ? '_blank' : null;
						case 'rel':
							return $url ? 'noreferrer noopener sponsored' : null;
					}
					return null;
				},
			)
		);
	}
);

/**
 * Pass the strip's team slug (block metadata "hopflyTeam") from the Query block down to its Post Template.
 */
add_filter(
	'render_block_context',
	static function ( array $context, array $parsed_block, $parent_block ): array {
		if ( 'core/post-template' === ( $parsed_block['blockName'] ?? '' ) && $parent_block instanceof \WP_Block && 'core/query' === $parent_block->name ) {
			$team = $parent_block->parsed_block['attrs']['metadata']['hopflyTeam'] ?? '';
			if ( $team ) {
				$context['hopflyTeam'] = sanitize_title( $team );
			}
		}
		return $context;
	},
	10,
	3
);

add_filter(
	'query_loop_block_query_vars',
	static function ( array $query, $block ): array {
		if ( 'hopfly_sponsor' !== ( $query['post_type'] ?? '' ) ) {
			return $query;
		}
		$team = $block->context['hopflyTeam'] ?? '';
		if ( $team ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => 'hopfly_team',
					'field'    => 'slug',
					'terms'    => $team,
				),
			);
		}
		$query['orderby'] = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
		return $query;
	},
	10,
	2
);

/**
 * Sponsors without a logo show their name in the tile instead of an empty box.
 */
add_filter(
	'render_block_core/post-featured-image',
	static function ( string $content, array $block, $instance ): string {
		$class = $block['attrs']['className'] ?? '';
		if ( '' !== trim( $content ) || ! str_contains( $class, 'hopfly-sponsor-logo' ) ) {
			return $content;
		}
		$post_id = $instance->context['postId'] ?? 0;
		if ( ! $post_id || 'hopfly_sponsor' !== get_post_type( $post_id ) ) {
			return $content;
		}
		return '<figure class="wp-block-post-featured-image hopfly-sponsor-logo"><span class="hopfly-sponsor-name">' . esc_html( get_the_title( $post_id ) ) . '</span></figure>';
	},
	10,
	3
);

/**
 * No standalone sponsor pages: a direct request for a single sponsor is a 404.
 */
add_action(
	'template_redirect',
	static function () {
		if ( is_singular( 'hopfly_sponsor' ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);
