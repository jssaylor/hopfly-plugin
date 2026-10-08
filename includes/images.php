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
