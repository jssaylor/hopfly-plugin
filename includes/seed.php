<?php
/**
 * Starter terms and content from the project file. Safe to run more than once.
 * Run manually with: wp eval 'HopFly\Plugin\seed_content();'
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Teams and event types.
 */
function seed_terms(): void {
	foreach ( default_teams() as $slug => $name ) {
		if ( ! term_exists( $slug, 'hopfly_team' ) ) {
			wp_insert_term( $name, 'hopfly_team', array( 'slug' => $slug ) );
		}
	}
	foreach ( array( 'group-ride' => 'Group rides', 'charity-ride' => 'Charity rides', 'fundraiser' => 'Fundraisers' ) as $slug => $name ) {
		if ( ! term_exists( $slug, 'hopfly_event_type' ) ) {
			wp_insert_term( $name, 'hopfly_event_type', array( 'slug' => $slug ) );
		}
	}
}

/**
 * Sponsor names per team (from the project file) and the first events. No logos, links or times are invented.
 */
function seed_content(): array {
	register_post_types();
	seed_terms();
	$made = array( 'sponsors' => 0, 'events' => 0 );

	$sponsors = array(
		'Keffer Mazda'            => array( 'club', 'racing', 'jianna' ),
		'Hincapie Sportswear'     => array( 'club', 'racing', 'jianna', 'juniors', 'cross-and-brew' ),
		'BikeSource Charlotte'    => array( 'club', 'racing' ),
		'HopFly Brewing Company'  => array( 'club', 'cross-and-brew' ),
		'Charlotte Knights'       => array( 'club', 'juniors', 'cross-and-brew' ),
		'Weldon Weaver'           => array( 'club', 'juniors', 'cross-and-brew' ),
		'Jianna Restaurant Greenville' => array( 'jianna' ),
		'Carolina Drive In'       => array( 'jianna' ),
	);
	$order = 0;
	foreach ( $sponsors as $name => $teams ) {
		++$order;
		if ( get_page_by_title( $name, OBJECT, 'hopfly_sponsor' ) ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'hopfly_sponsor', 'post_status' => 'publish', 'post_title' => $name, 'menu_order' => $order ) );
		if ( $id && ! is_wp_error( $id ) ) {
			wp_set_object_terms( $id, $teams, 'hopfly_team' );
			++$made['sponsors'];
		}
	}

	$events = array(
		array( 'HopFly Bike & Brew', 'group-ride', array( 'hopfly_recurrence' => 'saturday', 'hopfly_venue' => 'HopFly Brewing Company', 'hopfly_address' => '1327 S Mint Street, Charlotte, NC' ), 'Free Saturday group ride from HopFly Brewing Company. Check this page for the week\'s start time.' ),
		array( 'Ride to End ALZ® South Carolina', 'charity-ride', array( 'hopfly_start_date' => '2027-05-21', 'hopfly_end_date' => '2027-05-23', 'hopfly_venue' => 'Simpsonville, SC', 'hopfly_reg_label' => 'Join Team HopFly DMF' ), 'Three days. 255 miles. Simpsonville to Newberry to Orangeburg to Charleston. Team HopFly DMF, with the David Moore Foundation.' ),
		array( '24 Hours of Booty', 'charity-ride', array( 'hopfly_start_date' => '2027-07-23', 'hopfly_end_date' => '2027-07-24', 'hopfly_venue' => 'Myers Park, Charlotte, NC', 'hopfly_reg_url' => 'https://events.24foundation.org/team/815203' ), 'A non-competitive, 24-hour cycling and walking event in Charlotte\'s Myers Park, run by 24 Foundation to raise money for cancer navigation and survivorship.' ),
		array( 'Bike MS: Tour to Tanglewood', 'charity-ride', array( 'hopfly_start_date' => '2027-09-01', 'hopfly_date_text' => 'September 2027 · dates coming soon', 'hopfly_venue' => 'Tanglewood Park, Clemmons, NC', 'hopfly_reg_url' => 'https://events.nationalmssociety.org/teams/99652' ), 'Ride with HopFly for a world free of MS. The event starts and finishes at Tanglewood Park in Clemmons, NC.' ),
		array( 'Bike Luck', 'charity-ride', array( 'hopfly_date_text' => '2027 dates coming soon', 'hopfly_venue' => 'Camp LUCK' ), 'Ride with HopFly to support children with heart disease at Camp LUCK.' ),
	);
	foreach ( $events as $e ) {
		if ( get_page_by_title( $e[0], OBJECT, 'hopfly_event' ) ) {
			continue;
		}
		$id = wp_insert_post( array( 'post_type' => 'hopfly_event', 'post_status' => 'publish', 'post_title' => $e[0], 'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $e[3] ) . '</p><!-- /wp:paragraph -->' ) );
		if ( $id && ! is_wp_error( $id ) ) {
			wp_set_object_terms( $id, $e[1], 'hopfly_event_type' );
			foreach ( $e[2] as $k => $v ) {
				update_post_meta( $id, $k, $v );
			}
			++$made['events'];
		}
	}
	return $made;
}

/**
 * WooCommerce shop categories from the project file. Needs WooCommerce. Returns the number created.
 */
function ensure_shop_categories(): int {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return 0;
	}
	$made = 0;
	foreach ( array( 'club-kits' => 'Club Kits', 'rtea' => 'Ride to End ALZ® South Carolina', 'support' => 'Support' ) as $slug => $name ) {
		if ( ! term_exists( $slug, 'product_cat' ) ) {
			$result = wp_insert_term( $name, 'product_cat', array( 'slug' => $slug ) );
			if ( ! is_wp_error( $result ) ) {
				++$made;
			}
		}
	}
	return $made;
}

/**
 * Example products: a pre-order kit, a donation and a tip. Clearly named, marked with _hopfly_example = yes,
 * safe to run more than once, and safe to delete. Needs WooCommerce. Returns the number created.
 */
function seed_example_products(): int {
	if ( ! class_exists( 'WC_Product_Variable' ) ) {
		return 0;
	}
	ensure_shop_categories();
	$made = 0;
	$cat  = static function ( string $slug ): array {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		return $term ? array( (int) $term->term_id ) : array();
	};
	$exists = static function ( string $name ): bool {
		$found = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'title' => $name, 'numberposts' => 1, 'fields' => 'ids' ) );
		return (bool) $found;
	};

	// A photo for the kit (the theme's circle mark; replace it with real kit photos).
	$image_id = 0;
	$icon     = function_exists( 'get_theme_file_path' ) ? get_theme_file_path( 'assets/icons/icon-512.png' ) : '';
	if ( $icon && file_exists( $icon ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( 'hopfly-example.png' );
		copy( $icon, $tmp );
		$image_id = media_handle_sideload( array( 'name' => 'hopfly-example-kit-photo.png', 'tmp_name' => $tmp ), 0, 'Example kit photo (replace me)' );
		if ( is_wp_error( $image_id ) ) {
			$image_id = 0;
		}
	}

	/**
	 * Variable product with one or two attributes; every combination becomes a variation.
	 *
	 * @param array $attrs name => values[]; $price per variation; $extra callback(WC_Product_Variable).
	 */
	$build_variable = static function ( string $name, array $attrs, string $price, array $opts = array() ) use ( &$made ): int {
		$product = new \WC_Product_Variable();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_short_description( $opts['short'] ?? '' );
		$product->set_description( $opts['long'] ?? '' );
		$product->set_category_ids( $opts['cats'] ?? array() );
		$product->set_virtual( ! empty( $opts['virtual'] ) );
		$product->set_tax_status( ! empty( $opts['virtual'] ) ? 'none' : 'taxable' );
		if ( ! empty( $opts['image'] ) ) {
			$product->set_image_id( (int) $opts['image'] );
		}
		$attributes = array();
		$position   = 0;
		foreach ( $attrs as $label => $values ) {
			$attribute = new \WC_Product_Attribute();
			$attribute->set_name( $label );
			$attribute->set_options( $values );
			$attribute->set_position( $position++ );
			$attribute->set_visible( true );
			$attribute->set_variation( true );
			$attributes[] = $attribute;
		}
		$product->set_attributes( $attributes );
		$id = $product->save();

		$combos = array( array() );
		foreach ( $attrs as $label => $values ) {
			$next = array();
			foreach ( $combos as $combo ) {
				foreach ( $values as $value ) {
					$next[] = $combo + array( sanitize_title( $label ) => $value );
				}
			}
			$combos = $next;
		}
		foreach ( $combos as $combo ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $id );
			$variation->set_attributes( $combo );
			// Amount products price each variation by its own amount ("$25" -> 25).
			$amount = isset( $combo['amount'] ) ? (float) preg_replace( '/[^0-9.]/', '', $combo['amount'] ) : (float) $price;
			$variation->set_regular_price( (string) $amount );
			$variation->set_status( 'publish' );
			$variation->set_virtual( ! empty( $opts['virtual'] ) );
			$variation->save();
		}
		\WC_Product_Variable::sync( $id );
		update_post_meta( $id, '_hopfly_example', 'yes' );
		++$made;
		return $id;
	};

	if ( ! $exists( 'Example Club Kit' ) ) {
		$sizes = array( 'XS', 'S', 'M', 'L', 'XL' );
		$id    = $build_variable(
			'Example Club Kit',
			array( 'Jersey Size' => $sizes, 'Bib Size' => $sizes ),
			'185',
			array(
				'short' => 'An example pre-order kit. Pick your jersey and bib sizes. Replace this text, the photo and the price.',
				'long'  => '',
				'cats'  => $cat( 'club-kits' ),
				'image' => $image_id,
			)
		);
		update_post_meta( $id, '_hopfly_preorder', 'yes' );
		update_post_meta( $id, '_hopfly_preorder_until', ( new \DateTimeImmutable( '+45 days', wp_timezone() ) )->format( 'Y-m-d' ) );
		update_post_meta( $id, '_hopfly_ship_weeks', 6 );
		update_post_meta( $id, '_hopfly_size_chart_url', home_url( '/about/' ) );
	}

	if ( ! $exists( 'Example Donation' ) ) {
		$build_variable(
			'Example Donation',
			array( 'Amount' => array( '$10', '$25', '$50', '$100', '$250', '$500' ) ),
			'0',
			array(
				'short'   => 'Choose an amount.',
				'long'    => '<!-- wp:paragraph --><p>Gifts over $500? <a href="' . esc_url( home_url( '/contact/' ) ) . '">Contact us</a>.</p><!-- /wp:paragraph -->',
				'cats'    => $cat( 'support' ),
				'virtual' => true,
			)
		);
	}

	if ( ! $exists( 'Example Tip Your SAG' ) ) {
		$build_variable(
			'Example Tip Your SAG',
			array( 'Amount' => array( '$5', '$10', '$25', '$50' ) ),
			'0',
			array(
				'short'   => 'Thank your SAG driver for keeping the ride rolling. Choose an amount, or add more than one.',
				'long'    => '',
				'cats'    => $cat( 'support' ),
				'virtual' => true,
			)
		);
	}
	return $made;
}
