<?php
/**
 * Event post type, event types, meta fields, editor panels and admin columns.
 *
 * One source of truth for dates and times. Nothing here hardcodes a time.
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registered from register_post_types() (see sponsors.php) so activation can call it too.
 */
function register_event_types(): void {
	register_post_type(
		'hopfly_event',
		array(
			'labels'       => array(
				'name'          => __( 'Events', 'hopfly' ),
				'singular_name' => __( 'Event', 'hopfly' ),
				'add_new_item'  => __( 'Add new event', 'hopfly' ),
				'edit_item'     => __( 'Edit event', 'hopfly' ),
				'menu_name'     => __( 'Events', 'hopfly' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'events', 'with_front' => false ),
		)
	);

	register_taxonomy(
		'hopfly_event_type',
		'hopfly_event',
		array(
			'labels'            => array(
				'name'          => __( 'Event types', 'hopfly' ),
				'singular_name' => __( 'Event type', 'hopfly' ),
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		)
	);

	$string = static function ( string $sanitize = 'sanitize_text_field' ): array {
		return array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '',
			'sanitize_callback' => $sanitize,
			'auth_callback'     => static fn() => current_user_can( 'edit_posts' ),
		);
	};
	$fields = array(
		'hopfly_start_date'   => 'sanitize_date',
		'hopfly_end_date'     => 'sanitize_date',
		'hopfly_start_time'   => 'sanitize_time',
		'hopfly_end_time'     => 'sanitize_time',
		'hopfly_date_text'    => 'sanitize_text_field',
		'hopfly_venue'        => 'sanitize_text_field',
		'hopfly_address'      => 'sanitize_text_field',
		'hopfly_map_url'      => 'esc_url_raw',
		'hopfly_reg_url'      => 'esc_url_raw',
		'hopfly_reg_label'    => 'sanitize_text_field',
		'hopfly_status'       => 'sanitize_status',
		'hopfly_status_note'  => 'sanitize_text_field',
		'hopfly_recurrence'   => 'sanitize_recurrence',
		'hopfly_rec_until'    => 'sanitize_date',
		'hopfly_overrides'    => 'sanitize_overrides',
	);
	foreach ( $fields as $key => $cb ) {
		$cb = str_contains( $cb, '\\' ) || function_exists( $cb ) ? $cb : __NAMESPACE__ . '\\' . $cb;
		register_post_meta( 'hopfly_event', $key, $string( $cb ) );
	}
	register_post_meta(
		'hopfly_event',
		'hopfly_all_day',
		array(
			'type'          => 'boolean',
			'single'        => true,
			'show_in_rest'  => true,
			'default'       => false,
			'auth_callback' => static fn() => current_user_can( 'edit_posts' ),
		)
	);
}

function sanitize_date( $value ): string {
	$value = (string) $value;
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
}

function sanitize_time( $value ): string {
	$value = (string) $value;
	return preg_match( '/^\d{2}:\d{2}$/', $value ) ? $value : '';
}

function sanitize_status( $value ): string {
	return in_array( $value, array( 'canceled', 'postponed' ), true ) ? $value : 'scheduled';
}

function sanitize_recurrence( $value ): string {
	return 'saturday' === $value ? 'saturday' : '';
}

/**
 * Per-date overrides for the recurring ride, stored as JSON: [{date, status, time, note}].
 */
function sanitize_overrides( $value ): string {
	$rows = json_decode( (string) $value, true );
	if ( ! is_array( $rows ) ) {
		return '[]';
	}
	$clean = array();
	foreach ( $rows as $row ) {
		$date = sanitize_date( $row['date'] ?? '' );
		if ( ! $date ) {
			continue;
		}
		$clean[] = array(
			'date'   => $date,
			'status' => sanitize_status( $row['status'] ?? '' ),
			'time'   => sanitize_time( $row['time'] ?? '' ),
			'note'   => sanitize_text_field( $row['note'] ?? '' ),
		);
	}
	return wp_json_encode( $clean );
}

/**
 * Editor panels (sidebar) for events and sponsors. Plain JavaScript, no build step.
 */
add_action(
	'enqueue_block_editor_assets',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, array( 'hopfly_event', 'hopfly_sponsor' ), true ) ) {
			return;
		}
		wp_enqueue_script(
			'hopfly-editor-panels',
			HOPFLY_PLUGIN_URL . 'assets/editor-panels.js',
			array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n' ),
			VERSION,
			true
		);
	}
);

/**
 * Admin list columns: date and status.
 */
add_filter(
	'manage_hopfly_event_posts_columns',
	static function ( array $cols ): array {
		$cols['hopfly_when']   = __( 'When', 'hopfly' );
		$cols['hopfly_status'] = __( 'Status', 'hopfly' );
		return $cols;
	}
);
add_action(
	'manage_hopfly_event_posts_custom_column',
	static function ( string $col, int $post_id ): void {
		if ( 'hopfly_when' === $col ) {
			$event = get_event( $post_id );
			echo esc_html( $event ? ( $event['recurrence'] ? __( 'Every Saturday', 'hopfly' ) : format_event_dates( $event ) ) : '' );
		}
		if ( 'hopfly_status' === $col ) {
			echo esc_html( ucfirst( (string) get_post_meta( $post_id, 'hopfly_status', true ) ?: 'scheduled' ) );
		}
	},
	10,
	2
);
