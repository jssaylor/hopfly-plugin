<?php
/**
 * WooCommerce tweaks.
 *
 * Kits have Jersey Size x Bib Size variations (14 x 14 = 196). Above 30 variations WooCommerce
 * switches to AJAX lookups; this raises the limit so the dropdowns stay instant.
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_ajax_variation_threshold',
	static fn(): int => 250
);

/* -------------------------------------------------------------------------------------------------
 * Pre-order products: per-product fields, the pre-order blocks, a closing window, the quantity stepper.
 * Fields (General tab): pre-order checkbox, window end date, weeks to ship, size chart link.
 * ----------------------------------------------------------------------------------------------- */

/**
 * Pre-order data for a product, or null if it is not a pre-order product.
 */
function preorder_data( int $product_id ): ?array {
	if ( 'yes' !== get_post_meta( $product_id, '_hopfly_preorder', true ) ) {
		return null;
	}
	$until = (string) get_post_meta( $product_id, '_hopfly_preorder_until', true );
	$weeks = (int) get_post_meta( $product_id, '_hopfly_ship_weeks', true );
	return array(
		'until'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) ? $until : '',
		'weeks'  => $weeks > 0 ? $weeks : 0,
		'chart'  => (string) get_post_meta( $product_id, '_hopfly_size_chart_url', true ),
		'closed' => $until && $until < today(),
	);
}

add_action(
	'woocommerce_product_options_general_product_data',
	static function () {
		echo '<div class="options_group show_if_simple show_if_variable hopfly-preorder-fields">';
		woocommerce_wp_checkbox( array( 'id' => '_hopfly_preorder', 'label' => __( 'HopFly pre-order', 'hopfly' ), 'description' => __( 'Show the pre-order badge, window card and final-sale line; the button reads "Pre-order".', 'hopfly' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_hopfly_preorder_until', 'label' => __( 'Window closes', 'hopfly' ), 'type' => 'date', 'desc_tip' => true, 'description' => __( 'Last day to order. After this date the product can no longer be bought and the card says the window has closed.', 'hopfly' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_hopfly_ship_weeks', 'label' => __( 'Ships in about (weeks)', 'hopfly' ), 'type' => 'number', 'custom_attributes' => array( 'min' => 1, 'step' => 1 ), 'desc_tip' => true, 'description' => __( 'Shown as "ship in about N weeks" after the window closes.', 'hopfly' ) ) );
		woocommerce_wp_text_input( array( 'id' => '_hopfly_size_chart_url', 'label' => __( 'Size chart link', 'hopfly' ), 'type' => 'url', 'desc_tip' => true, 'description' => __( 'A page or PDF. Leave empty to hide the link.', 'hopfly' ) ) );
		echo '</div>';
	}
);

add_action(
	'woocommerce_process_product_meta',
	static function ( int $post_id ) {
		// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce verifies the nonce before this hook.
		update_post_meta( $post_id, '_hopfly_preorder', isset( $_POST['_hopfly_preorder'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_hopfly_preorder_until', sanitize_date( wp_unslash( $_POST['_hopfly_preorder_until'] ?? '' ) ) );
		update_post_meta( $post_id, '_hopfly_ship_weeks', absint( $_POST['_hopfly_ship_weeks'] ?? 0 ) );
		update_post_meta( $post_id, '_hopfly_size_chart_url', esc_url_raw( wp_unslash( $_POST['_hopfly_size_chart_url'] ?? '' ) ) );
		// phpcs:enable
	}
);

// The button says "Pre-order" on pre-order products.
add_filter(
	'woocommerce_product_single_add_to_cart_text',
	static function ( string $text, $product ): string {
		return preorder_data( $product->get_id() ) ? __( 'Pre-order', 'hopfly' ) : $text;
	},
	10,
	2
);

// When the window has closed, the product cannot be bought. (Parent id for variations.)
add_filter(
	'woocommerce_is_purchasable',
	static function ( bool $purchasable, $product ): bool {
		$id   = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$data = preorder_data( $id );
		return ( $data && $data['closed'] ) ? false : $purchasable;
	},
	10,
	2
);

/**
 * Pre-order block: badge, window card, final-sale line, size chart link.
 */
function render_product_preorder( array $attrs, string $content, \WP_Block $block ): string {
	wp_enqueue_style( 'hopfly-shop' );
	$post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
	$data    = preorder_data( $post_id );
	if ( ! $data ) {
		return '';
	}
	switch ( $attrs['part'] ?? 'notice' ) {
		case 'badge':
			return '<p class="hopfly-preorder-badge">' . esc_html__( 'Pre-order', 'hopfly' ) . '</p>';
		case 'final-sale':
			return '<p class="hopfly-final-sale"><strong>' . esc_html__( 'Final sale.', 'hopfly' ) . '</strong> ' . esc_html__( "Kits are made to order and can't be returned or exchanged.", 'hopfly' ) . '</p>';
		case 'size-chart':
			return $data['chart'] ? '<p class="hopfly-size-chart"><a href="' . esc_url( $data['chart'] ) . '">' . esc_html__( 'Size chart', 'hopfly' ) . '</a></p>' : '';
		default:
			$text = '';
			if ( $data['closed'] ) {
				$text = __( 'The pre-order window has closed.', 'hopfly' );
			} else {
				if ( $data['until'] ) {
					/* translators: %s: date such as "March 15". */
					$text = sprintf( __( 'Open now through %s.', 'hopfly' ), wp_date( 'F j', strtotime( $data['until'] . ' 12:00 UTC' ), new \DateTimeZone( 'UTC' ) ) );
				}
				if ( $data['weeks'] ) {
					/* translators: %d: number of weeks. */
					$text .= ( $text ? ' ' : '' ) . sprintf( _n( 'Kits are made after the window closes and ship in about %d week.', 'Kits are made after the window closes and ship in about %d weeks.', $data['weeks'], 'hopfly' ), $data['weeks'] );
				}
			}
			if ( '' === $text ) {
				return '';
			}
			return '<div class="hopfly-preorder-card" role="note"><span class="hopfly-preorder-card__eyebrow">' . esc_html__( 'Pre-order window', 'hopfly' ) . '</span><span class="hopfly-preorder-card__text">' . esc_html( $text ) . '</span></div>';
	}
}

/**
 * Product Gallery block: the product's photos in the slideshow.
 */
function render_product_gallery( array $attrs, string $content, \WP_Block $block ): string {
	$post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
	if ( ! $product ) {
		return '';
	}
	wp_enqueue_style( 'hopfly-slideshow-style' );
	wp_enqueue_script( 'hopfly-slideshow-view-script' );
	$ids = array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) ) );
	$slides = array();
	foreach ( array_values( array_unique( $ids ) ) as $id ) {
		$img = wp_get_attachment_image( $id, 'large', false, array( 'loading' => 0 === count( $slides ) ? 'eager' : 'lazy', 'decoding' => 'async' ) );
		if ( ! $img ) {
			continue;
		}
		$focal = attachment_focal( $id );
		if ( $focal ) {
			$img = str_replace( '<img ', sprintf( '<img style="object-position:%s%% %s%%" ', round( $focal['x'] * 100, 1 ), round( $focal['y'] * 100, 1 ) ), $img );
		}
		$slides[] = '<figure class="wp-block-image">' . $img . '</figure>';
	}
	if ( ! $slides ) {
		$slides[] = '<figure class="wp-block-image">' . wc_placeholder_img( 'large', array( 'alt' => $product->get_name() ) ) . '</figure>';
	}
	return slideshow_markup( $slides, sprintf( /* translators: %s: product name. */ __( '%s photos', 'hopfly' ), $product->get_name() ) );
}

/**
 * Size chart link inside the variations form, between the dropdowns and the quantity (matches the design).
 */
add_action(
	'woocommerce_before_single_variation',
	static function () {
		global $product;
		$data = $product ? preorder_data( $product->get_id() ) : null;
		if ( $data && $data['chart'] ) {
			wp_enqueue_style( 'hopfly-shop' );
			echo '<p class="hopfly-size-chart"><a href="' . esc_url( $data['chart'] ) . '">' . esc_html__( 'Size chart', 'hopfly' ) . '</a></p>';
		}
	}
);

/**
 * Quantity stepper: − and + buttons around WooCommerce's quantity input. Plain number input without JavaScript.
 */
add_action(
	'woocommerce_before_quantity_input_field',
	static function () {
		if ( is_product() ) {
			echo '<button type="button" class="hopfly-qty__btn" data-hopfly-qty="-1" aria-label="' . esc_attr__( 'Decrease quantity', 'hopfly' ) . '" hidden>−</button>';
		}
	}
);
add_action(
	'woocommerce_after_quantity_input_field',
	static function () {
		if ( is_product() ) {
			echo '<button type="button" class="hopfly-qty__btn" data-hopfly-qty="1" aria-label="' . esc_attr__( 'Increase quantity', 'hopfly' ) . '" hidden>+</button>';
		}
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_register_style( 'hopfly-shop', HOPFLY_PLUGIN_URL . 'assets/shop.css', array(), filemtime( HOPFLY_PLUGIN_DIR . 'assets/shop.css' ) );
		if ( function_exists( 'is_product' ) && is_product() ) {
			wp_enqueue_style( 'hopfly-shop' );
			wp_enqueue_script( 'hopfly-shop', HOPFLY_PLUGIN_URL . 'assets/shop.js', array(), filemtime( HOPFLY_PLUGIN_DIR . 'assets/shop.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
	}
);

/* ---- Per-image crop focus on the attachment (used by the product gallery and any image block with a media-library photo) ---- */

function attachment_focal( int $id ): ?array {
	$raw = (string) get_post_meta( $id, '_hopfly_focal', true );
	if ( ! preg_match( '/^(\d{1,3}),(\d{1,3})$/', $raw, $m ) ) {
		return null;
	}
	return array( 'x' => min( 100, (int) $m[1] ) / 100, 'y' => min( 100, (int) $m[2] ) / 100 );
}

add_filter(
	'attachment_fields_to_edit',
	static function ( array $fields, \WP_Post $post ): array {
		$focal = attachment_focal( $post->ID );
		$fields['hopfly_focal'] = array(
			'label' => __( 'Crop focus', 'hopfly' ),
			'input' => 'html',
			'html'  => sprintf(
				'<input type="text" name="attachments[%1$d][hopfly_focal]" value="%2$s" placeholder="50,50" style="width:90px"> <span class="description">%3$s</span>',
				$post->ID,
				esc_attr( $focal ? round( $focal['x'] * 100 ) . ',' . round( $focal['y'] * 100 ) : '' ),
				esc_html__( 'x,y in percent (0,0 = top-left). Keeps this point in view when the photo is cropped to a square.', 'hopfly' )
			),
		);
		return $fields;
	},
	10,
	2
);
add_filter(
	'attachment_fields_to_save',
	static function ( array $post, array $attachment ): array {
		if ( isset( $attachment['hopfly_focal'] ) ) {
			$val = trim( (string) $attachment['hopfly_focal'] );
			if ( preg_match( '/^\d{1,3}\s*,\s*\d{1,3}$/', $val ) ) {
				update_post_meta( $post['ID'], '_hopfly_focal', str_replace( ' ', '', $val ) );
			} elseif ( '' === $val ) {
				delete_post_meta( $post['ID'], '_hopfly_focal' );
			}
		}
		return $post;
	},
	10,
	2
);

