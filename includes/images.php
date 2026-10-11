<?php
/**
 * Image delivery: smaller originals, WebP sub-sizes, sensible `sizes` for full-width hero images.
 * Target: no photo on a page heavier than about 300 KB at 1440px.
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

// Scale very large uploads down (WordPress default is 2560px).
add_filter( 'big_image_size_threshold', static fn(): int => 2000 );

// Generate WebP for JPEG uploads (every generated size), where the server supports it.
add_filter(
	'image_editor_output_format',
	static function ( array $formats ): array {
		$formats['image/jpeg'] = 'image/webp';
		return $formats;
	}
);

add_filter( 'wp_editor_set_quality', static fn(): int => 80 );

/**
 * Full-width cover/hero images fill the viewport: tell the browser, so it does not pick a larger file than needed.
 */
add_filter(
	'wp_content_img_tag',
	static function ( string $tag ): string {
		if ( ! str_contains( $tag, 'wp-block-cover__image-background' ) || ! str_contains( $tag, 'srcset=' ) ) {
			return $tag;
		}
		$tags = new \WP_HTML_Tag_Processor( $tag );
		if ( $tags->next_tag( 'img' ) ) {
			$tags->set_attribute( 'sizes', '100vw' );
		}
		return $tags->get_updated_html();
	}
);

/* ---- Per-image crop focus on the attachment (used by the slideshow and any image block with a media-library photo) ---- */

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

