<?php
/**
 * Blocks: Upcoming Events, Event Details, Slideshow; plus the per-image focal point for core/image.
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_block_type( HOPFLY_PLUGIN_DIR . 'blocks/upcoming-events', array( 'render_callback' => __NAMESPACE__ . '\render_upcoming_events' ) );
		register_block_type( HOPFLY_PLUGIN_DIR . 'blocks/event-details', array( 'render_callback' => __NAMESPACE__ . '\render_event_details' ) );
		register_block_type( HOPFLY_PLUGIN_DIR . 'blocks/slideshow', array( 'render_callback' => __NAMESPACE__ . '\render_slideshow' ) );
	}
);

/**
 * Event styles load wherever an events block renders.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_register_style( 'hopfly-events', HOPFLY_PLUGIN_URL . 'assets/events.css', array(), VERSION );
	}
);

/**
 * Load the events styles inside the block editor too, so the live preview looks like the site.
 */
add_action(
	'enqueue_block_assets',
	static function () {
		if ( is_admin() ) {
			wp_enqueue_style( 'hopfly-events', HOPFLY_PLUGIN_URL . 'assets/events.css', array(), VERSION );
		}
	}
);

/**
 * One event row.
 */
function render_event_row( array $e, bool $photos ): string {
	[ $mon, $day, $long ] = tile_parts( $e );
	$status               = $e['status'];
	$classes              = array( 'hopfly-event' );
	if ( 'scheduled' !== $status ) {
		$classes[] = 'is-' . $status;
	}
	if ( $e['recurring'] ) {
		$classes[] = 'is-recurring';
	}
	$when  = format_event_dates( $e );
	$time  = 'canceled' === $status ? '' : format_event_time( $e );
	$venue = $e['venue'] ?: $e['address'];
	$has_reg = $e['reg_url'] && 'scheduled' === $status;
	$cta   = $has_reg ? ( $e['reg_label'] ?: __( 'Register', 'hopfly' ) ) : ( $e['recurring'] ? __( 'Ride details', 'hopfly' ) : __( 'Details', 'hopfly' ) );
	$href  = $has_reg ? $e['reg_url'] : $e['url'];

	ob_start();
	?>
	<li class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
		<div class="hopfly-event__date<?php echo $long ? ' is-long' : ''; ?>">
			<span class="hopfly-event__mon"><?php echo esc_html( $mon ); ?></span>
			<span class="hopfly-event__day"><?php echo esc_html( $day ); ?></span>
		</div>
		<?php if ( $photos && $e['thumb_id'] ) : ?>
			<div class="hopfly-event__photo"><?php echo wp_get_attachment_image( $e['thumb_id'], 'medium', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>
		<div class="hopfly-event__main">
			<div class="hopfly-event__titlerow">
				<a class="hopfly-event__title" href="<?php echo esc_url( $e['url'] ); ?>"><?php echo esc_html( $e['title'] ); ?></a>
				<?php if ( 'scheduled' !== $status ) : ?>
					<span class="hopfly-event__status is-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( 'canceled' === $status ? __( 'Canceled', 'hopfly' ) : __( 'Postponed', 'hopfly' ) ); ?></span>
				<?php endif; ?>
			</div>
			<p class="hopfly-event__meta">
				<?php if ( $when || $time ) : ?>
					<span class="hopfly-event__when"><?php echo esc_html( trim( $when . ( $time ? ' · ' . $time : '' ) ) ); ?></span>
				<?php endif; ?>
				<?php if ( $venue ) : ?>
					<span><?php echo esc_html( $venue ); ?></span>
				<?php endif; ?>
				<?php if ( $e['note'] ) : ?>
					<span><?php echo esc_html( $e['note'] ); ?></span>
				<?php elseif ( $e['recurring'] && 'scheduled' === $status ) : ?>
					<span><?php esc_html_e( 'Repeats every Saturday', 'hopfly' ); ?></span>
				<?php endif; ?>
			</p>
		</div>
		<a class="hopfly-event__cta<?php echo $has_reg ? ' is-primary' : ''; ?>" href="<?php echo esc_url( $href ); ?>"<?php echo $has_reg ? ' target="_blank" rel="noreferrer noopener"' : ''; ?> aria-label="<?php echo esc_attr( $cta . ': ' . $e['title'] . ( $when ? ', ' . $when : '' ) ); ?>"><?php echo esc_html( $cta ); ?></a>
	</li>
	<?php
	return (string) ob_get_clean();
}

/**
 * Upcoming Events block.
 *
 * @param array $attrs limit, type, grouped, photos, onGray.
 */
function render_upcoming_events( array $attrs ): string {
	wp_enqueue_style( 'hopfly-events' );
	$events = upcoming_events( (string) ( $attrs['type'] ?? '' ) );
	$limit  = (int) ( $attrs['limit'] ?? 0 );
	if ( $limit > 0 ) {
		$events = array_slice( $events, 0, $limit );
	}
	$photos  = ! empty( $attrs['photos'] );
	$wrapper = get_block_wrapper_attributes( array( 'class' => 'hopfly-events' . ( ! empty( $attrs['onGray'] ) ? ' is-on-gray' : '' ) ) );

	if ( ! $events ) {
		return '<div ' . $wrapper . '><p class="hopfly-events__empty">' . esc_html__( 'No upcoming events right now. Check back soon.', 'hopfly' ) . '</p></div>';
	}

	if ( ! empty( $attrs['grouped'] ) ) {
		$groups = array();
		foreach ( $events as $e ) {
			$groups[ $e['type_name'] ?: __( 'Events', 'hopfly' ) ][] = $e;
		}
		$html = '';
		foreach ( $groups as $name => $rows ) {
			$html .= '<section class="hopfly-events__group"><h2 class="hopfly-events__group-title">' . esc_html( $name ) . '</h2><ul class="hopfly-events__list">';
			foreach ( $rows as $e ) {
				$html .= render_event_row( $e, $photos );
			}
			$html .= '</ul></section>';
		}
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}

	$html = '<ul class="hopfly-events__list">';
	foreach ( $events as $e ) {
		$html .= render_event_row( $e, $photos );
	}
	return '<div ' . $wrapper . '>' . $html . '</ul></div>';
}

/**
 * Event Details block (single event template): status, date, time, venue + map link, registration.
 */
function render_event_details( array $attrs, string $content, \WP_Block $block ): string {
	wp_enqueue_style( 'hopfly-events' );
	$post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
	$event   = get_event( $post_id );
	if ( ! $event ) {
		return '';
	}
	if ( 'saturday' === $event['recurrence'] ) {
		$next = saturday_occurrences( $event );
		$event = $next ? $next[0] : $event;
	}
	$status = $event['status'];
	$time   = 'canceled' === $status ? '' : format_event_time( $event );
	$map    = map_link( $event );
	$past   = ! $event['recurring'] && is_past( $event );

	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes( array( 'class' => 'hopfly-event-details' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<?php if ( 'scheduled' !== $status ) : ?>
			<p class="hopfly-event-details__status"><span class="hopfly-event__status is-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( 'canceled' === $status ? __( 'Canceled', 'hopfly' ) : __( 'Postponed', 'hopfly' ) ); ?></span>
			<?php echo $event['note'] ? esc_html( $event['note'] ) : ''; ?></p>
		<?php endif; ?>
		<dl class="hopfly-event-details__list">
			<?php if ( format_event_dates( $event ) ) : ?>
				<dt><?php esc_html_e( 'Date', 'hopfly' ); ?></dt>
				<dd><?php echo esc_html( format_event_dates( $event ) ); ?></dd>
			<?php endif; ?>
			<?php if ( $time ) : ?>
				<dt><?php esc_html_e( 'Time', 'hopfly' ); ?></dt>
				<dd><?php echo esc_html( $time ); ?></dd>
			<?php endif; ?>
			<?php if ( $event['venue'] || $event['address'] ) : ?>
				<dt><?php esc_html_e( 'Where', 'hopfly' ); ?></dt>
				<dd>
					<?php echo esc_html( trim( $event['venue'] . ( $event['venue'] && $event['address'] ? ', ' : '' ) . $event['address'] ) ); ?>
					<?php if ( $map ) : ?>
						<br><a href="<?php echo esc_url( $map ); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'View on map (opens in a new tab)', 'hopfly' ); ?></a>
					<?php endif; ?>
				</dd>
			<?php endif; ?>
		</dl>
		<?php if ( $event['reg_url'] && 'scheduled' === $status && ! $past ) : ?>
			<p class="hopfly-event-details__cta"><a class="hopfly-event__cta is-primary" href="<?php echo esc_url( $event['reg_url'] ); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html( $event['reg_label'] ?: __( 'Register', 'hopfly' ) ); ?></a></p>
		<?php elseif ( $event['reg_url'] ) : ?>
			<p class="hopfly-event-details__cta"><span class="hopfly-event__cta is-disabled" aria-disabled="true"><?php esc_html_e( 'Registration closed', 'hopfly' ); ?></span></p>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Slideshow block: wraps the inner Image blocks in a swipeable, no-autoplay carousel.
 */
function render_slideshow( array $attrs, string $content, \WP_Block $block ): string {
	$slides = array();
	foreach ( $block->inner_blocks as $inner ) {
		$html = trim( $inner->render() );
		if ( '' !== $html ) {
			$slides[] = $html;
		}
	}
	if ( ! $slides ) {
		return '';
	}
	$total = count( $slides );
	$label = $attrs['label'] ?? __( 'Photo slideshow', 'hopfly' );
	$pad   = static fn( int $n ): string => str_pad( (string) $n, 2, '0', STR_PAD_LEFT );
	$arrow = static fn( string $dir ): string => '<svg width="10" height="16" viewBox="0 0 10 16" aria-hidden="true" focusable="false"><polyline points="' . ( 'prev' === $dir ? '8,2 2,8 8,14' : '2,2 8,8 2,14' ) . '" fill="none" stroke="currentColor" stroke-width="2"/></svg>';

	$wrapper = get_block_wrapper_attributes(
		array(
			'class'                 => 'hopfly-slideshow' . ( ! empty( $attrs['dark'] ) ? ' is-dark' : '' ),
			'role'                  => 'region',
			'aria-roledescription'  => 'carousel',
			'aria-label'            => $label,
			'data-hopfly-slideshow' => '',
		)
	);
	$html  = '<div ' . $wrapper . '>';
	$html .= '<div class="hopfly-slideshow__viewport"><div class="hopfly-slideshow__track" tabindex="0">';
	foreach ( $slides as $i => $slide ) {
		/* translators: 1: slide number, 2: total slides. */
		$html .= '<div class="hopfly-slideshow__slide" role="group" aria-roledescription="slide" aria-label="' . esc_attr( sprintf( __( '%1$d of %2$d', 'hopfly' ), $i + 1, $total ) ) . '">' . $slide . '</div>';
	}
	$html .= '</div>';
	if ( $total > 1 ) {
		$html .= '<button type="button" class="hopfly-slideshow__btn hopfly-slideshow__btn--prev" aria-label="' . esc_attr__( 'Previous photo', 'hopfly' ) . '" disabled>' . $arrow( 'prev' ) . '</button>';
		$html .= '<button type="button" class="hopfly-slideshow__btn hopfly-slideshow__btn--next" aria-label="' . esc_attr__( 'Next photo', 'hopfly' ) . '">' . $arrow( 'next' ) . '</button>';
	}
	$html .= '</div>';
	if ( $total > 1 ) {
		$html .= '<div class="hopfly-slideshow__status"><span class="hopfly-slideshow__counter" aria-live="polite">' . esc_html( $pad( 1 ) . ' / ' . $pad( $total ) ) . '</span>';
		$html .= '<div class="hopfly-slideshow__bar" aria-hidden="true"><div class="hopfly-slideshow__fill" style="width:' . esc_attr( (string) round( 100 / $total, 2 ) ) . '%"></div></div></div>';
	}
	return $html . '</div>';
}

/**
 * Per-image focal point: core/image gets a "hopflyFocal" attribute (set in the editor); render it as object-position.
 */
add_filter(
	'register_block_type_args',
	static function ( array $args, string $name ): array {
		if ( 'core/image' === $name ) {
			$args['attributes']['hopflyFocal'] = array( 'type' => 'object' );
		}
		return $args;
	},
	10,
	2
);

add_filter(
	'render_block_core/image',
	static function ( string $content, array $block ): string {
		$focal = $block['attrs']['hopflyFocal'] ?? null;
		if ( ! is_array( $focal ) || ! isset( $focal['x'], $focal['y'] ) || '' === $content ) {
			return $content;
		}
		$tags = new \WP_HTML_Tag_Processor( $content );
		if ( $tags->next_tag( 'img' ) ) {
			$style = rtrim( (string) $tags->get_attribute( 'style' ), '; ' );
			$style = ( $style ? $style . ';' : '' ) . sprintf( 'object-position:%s%% %s%%', round( (float) $focal['x'] * 100, 1 ), round( (float) $focal['y'] * 100, 1 ) );
			$tags->set_attribute( 'style', $style );
		}
		return $tags->get_updated_html();
	},
	10,
	2
);

add_action(
	'enqueue_block_editor_assets',
	static function () {
		wp_enqueue_script(
			'hopfly-image-focal',
			HOPFLY_PLUGIN_URL . 'assets/image-focal.js',
			array( 'wp-hooks', 'wp-compose', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			VERSION,
			true
		);
	}
);
