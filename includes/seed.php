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
