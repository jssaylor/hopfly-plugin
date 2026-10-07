<?php
/**
 * Event model: reading events, the Saturday recurrence rule, date formatting.
 *
 * Recurrence is deliberately small: one event can repeat weekly on Saturday, with one editable start time
 * (it changes with the season) and per-date overrides for cancellations or time changes.
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Normalized event data for a post ID, or null.
 */
function get_event( int $post_id ): ?array {
	$post = get_post( $post_id );
	if ( ! $post || 'hopfly_event' !== $post->post_type ) {
		return null;
	}
	$m = static fn( string $key ): string => (string) get_post_meta( $post_id, $key, true );

	$types = get_the_terms( $post_id, 'hopfly_event_type' );
	$type  = ( $types && ! is_wp_error( $types ) ) ? $types[0] : null;

	return array(
		'id'         => $post_id,
		'title'      => get_the_title( $post ),
		'url'        => get_permalink( $post ),
		'type_slug'  => $type ? $type->slug : '',
		'type_name'  => $type ? $type->name : '',
		'start'      => $m( 'hopfly_start_date' ),
		'end'        => $m( 'hopfly_end_date' ),
		'start_time' => $m( 'hopfly_start_time' ),
		'end_time'   => $m( 'hopfly_end_time' ),
		'all_day'    => (bool) get_post_meta( $post_id, 'hopfly_all_day', true ),
		'date_text'  => $m( 'hopfly_date_text' ),
		'venue'      => $m( 'hopfly_venue' ),
		'address'    => $m( 'hopfly_address' ),
		'map_url'    => $m( 'hopfly_map_url' ),
		'reg_url'    => $m( 'hopfly_reg_url' ),
		'reg_label'  => $m( 'hopfly_reg_label' ),
		'status'     => $m( 'hopfly_status' ) ?: 'scheduled',
		'note'       => $m( 'hopfly_status_note' ),
		'recurrence' => $m( 'hopfly_recurrence' ),
		'rec_until'  => $m( 'hopfly_rec_until' ),
		'overrides'  => json_decode( $m( 'hopfly_overrides' ) ?: '[]', true ) ?: array(),
		'thumb_id'   => (int) get_post_thumbnail_id( $post_id ),
		'recurring'  => false,
	);
}

/**
 * Today's date in the site's timezone (Y-m-d).
 */
function today(): string {
	return ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
}

/**
 * Current time in the site's timezone (H:i).
 */
function now_time(): string {
	return ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'H:i' );
}

/**
 * Next occurrences of a weekly Saturday event: up to the first non-canceled date (so a canceled
 * Saturday is shown as canceled and the following ride appears right after it).
 *
 * @param array $event Normalized event.
 * @param int   $max   Safety cap on dates generated.
 * @return array[] Event-shaped rows, one per date.
 */
function saturday_occurrences( array $event, int $max = 6 ): array {
	$tz       = wp_timezone();
	$day      = new \DateTimeImmutable( 'today', $tz );
	$out      = array();
	$overrides = array();
	foreach ( $event['overrides'] as $row ) {
		$overrides[ $row['date'] ?? '' ] = $row;
	}
	// Move to the first Saturday on or after today.
	$offset = ( 6 - (int) $day->format( 'w' ) + 7 ) % 7;
	$day    = $day->modify( "+{$offset} days" );

	for ( $i = 0; $i < $max; $i++, $day = $day->modify( '+7 days' ) ) {
		$date = $day->format( 'Y-m-d' );
		if ( $event['rec_until'] && $date > $event['rec_until'] ) {
			break;
		}
		$row               = $event;
		$row['recurring']  = true;
		$row['start']      = $date;
		$row['end']        = '';
		$row['occurrence'] = $date;
		$ov                = $overrides[ $date ] ?? null;
		if ( $ov ) {
			$row['status'] = $ov['status'] ?: 'scheduled';
			$row['note']   = $ov['note'] ?? '';
			if ( ! empty( $ov['time'] ) ) {
				$row['start_time'] = $ov['time'];
			}
		} else {
			$row['status'] = 'scheduled';
			$row['note']   = '';
		}
		// Today's ride that has already started is no longer "upcoming".
		if ( $date === today() && $row['start_time'] && $row['start_time'] < now_time() && 'scheduled' === $row['status'] ) {
			continue;
		}
		$out[] = $row;
		if ( 'canceled' !== $row['status'] && 'postponed' !== $row['status'] ) {
			break;
		}
	}
	return $out;
}

/**
 * All upcoming events (one-off events that have not ended, plus recurring occurrences), sorted by date.
 *
 * @param string $type Event type slug, or '' for all.
 */
function upcoming_events( string $type = '' ): array {
	$args = array(
		'post_type'      => 'hopfly_event',
		'post_status'    => 'publish',
		'posts_per_page' => 100,
		'no_found_rows'  => true,
		'fields'         => 'ids',
	);
	if ( $type ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'hopfly_event_type', 'field' => 'slug', 'terms' => $type ) );
	}
	$today = today();
	$rows  = array();
	foreach ( get_posts( $args ) as $id ) {
		$event = get_event( (int) $id );
		if ( ! $event ) {
			continue;
		}
		if ( 'saturday' === $event['recurrence'] ) {
			array_push( $rows, ...saturday_occurrences( $event ) );
			continue;
		}
		$last = $event['end'] ?: $event['start'];
		if ( $last && $last < $today ) {
			continue;
		}
		$rows[] = $event;
	}
	usort(
		$rows,
		static function ( array $a, array $b ): int {
			// Undated events (dates to come) sort last.
			$ka = ( $a['start'] ?: '9999-12-31' ) . ' ' . ( $a['start_time'] ?: '00:00' );
			$kb = ( $b['start'] ?: '9999-12-31' ) . ' ' . ( $b['start_time'] ?: '00:00' );
			return $ka <=> $kb;
		}
	);
	return $rows;
}

/**
 * "May 21–23, 2027", "Jun 30 – Jul 2, 2027", or "Sat, Oct 10". A custom date text always wins.
 */
function format_event_dates( array $e, bool $with_weekday = true ): string {
	if ( $e['date_text'] ) {
		return $e['date_text'];
	}
	if ( ! $e['start'] ) {
		return '';
	}
	$tz    = wp_timezone();
	$start = new \DateTimeImmutable( $e['start'], $tz );
	$end   = $e['end'] ? new \DateTimeImmutable( $e['end'], $tz ) : null;
	$year  = (int) $start->format( 'Y' ) !== (int) ( new \DateTimeImmutable( 'now', $tz ) )->format( 'Y' );

	if ( ! $end || $end->format( 'Y-m-d' ) === $start->format( 'Y-m-d' ) ) {
		return $start->format( ( $with_weekday ? 'D, ' : '' ) . 'M j' . ( $year ? ', Y' : '' ) );
	}
	if ( $start->format( 'Y-m' ) === $end->format( 'Y-m' ) ) {
		return $start->format( 'M j' ) . '–' . $end->format( 'j' ) . ', ' . $start->format( 'Y' );
	}
	if ( $start->format( 'Y' ) === $end->format( 'Y' ) ) {
		return $start->format( 'M j' ) . ' – ' . $end->format( 'M j' ) . ', ' . $start->format( 'Y' );
	}
	return $start->format( 'M j, Y' ) . ' – ' . $end->format( 'M j, Y' );
}

/**
 * Formatted time range ("8:00 AM" or "8:00 AM – 12:00 PM"), or '' when no time is set.
 */
function format_event_time( array $e ): string {
	if ( $e['all_day'] || ! $e['start_time'] ) {
		return $e['all_day'] ? __( 'All day', 'hopfly' ) : '';
	}
	$fmt = static fn( string $t ): string => wp_date( 'g:i A', strtotime( '1970-01-01 ' . $t . ' UTC' ), new \DateTimeZone( 'UTC' ) );
	return $fmt( $e['start_time'] ) . ( $e['end_time'] ? ' – ' . $fmt( $e['end_time'] ) : '' );
}

/**
 * Date tile parts: [top label, big text, long?].
 */
function tile_parts( array $e ): array {
	if ( ! $e['start'] ) {
		return array( __( 'Dates', 'hopfly' ), 'TBA', false );
	}
	$tz    = wp_timezone();
	$start = new \DateTimeImmutable( $e['start'], $tz );
	$end   = $e['end'] ? new \DateTimeImmutable( $e['end'], $tz ) : null;
	if ( $e['date_text'] && ! $end ) {
		// "September 2027 · dates coming soon": show the month and year.
		return array( $start->format( 'M' ), $start->format( 'Y' ), true );
	}
	$top = ( $e['recurring'] ? 'Sat · ' : '' ) . $start->format( 'M' );
	if ( ! $end || $end->format( 'Y-m-d' ) === $start->format( 'Y-m-d' ) ) {
		return array( $top, $start->format( 'j' ), false );
	}
	if ( $start->format( 'Y-m' ) === $end->format( 'Y-m' ) ) {
		return array( $start->format( 'M Y' ), $start->format( 'j' ) . '–' . $end->format( 'j' ), true );
	}
	return array( $start->format( 'M' ) . '–' . $end->format( 'M' ), $start->format( 'j' ) . '–' . $end->format( 'j' ), true );
}

/**
 * Map link: the explicit URL, otherwise a Google Maps search for venue + address.
 */
function map_link( array $e ): string {
	if ( $e['map_url'] ) {
		return $e['map_url'];
	}
	$query = trim( $e['venue'] . ' ' . $e['address'] );
	return $query ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query ) : '';
}

/**
 * Whether an event (or occurrence) has finished.
 */
function is_past( array $e ): bool {
	$last = $e['end'] ?: $e['start'];
	return $last && $last < today();
}
