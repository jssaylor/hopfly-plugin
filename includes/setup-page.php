<?php
/**
 * Tools > HopFly Setup: one button creates the starter pages, teams, sponsors, events, forms and shop
 * categories. Safe to run more than once: it only adds what is missing and never overwrites anything.
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_management_page( __( 'HopFly Setup', 'hopfly' ), __( 'HopFly Setup', 'hopfly' ), 'manage_options', 'hopfly-setup', __NAMESPACE__ . '\render_setup_page' );
	}
);

add_action(
	'admin_post_hopfly_setup',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'hopfly' ) );
		}
		check_admin_referer( 'hopfly_setup' );
		$out = array();
		if ( function_exists( '\HopFly\Theme\seed_pages' ) ) {
			$out['pages'] = \HopFly\Theme\seed_pages();
		}
		$out += seed_content();
		if ( class_exists( 'GFAPI' ) ) {
			$out['forms']        = count( ensure_forms() );
			$out['forms updated'] = sync_form_copy();
		}
		$out['categories'] = ensure_shop_categories();
		set_transient( 'hopfly_setup_result', $out, 60 );
		wp_safe_redirect( admin_url( 'tools.php?page=hopfly-setup&done=1' ) );
		exit;
	}
);

/**
 * Status rows: label, ok?, detail.
 */
function setup_status(): array {
	$rows   = array();
	$theme  = function_exists( '\HopFly\Theme\page_set' );
	if ( $theme ) {
		$missing = 0;
		foreach ( array_keys( \HopFly\Theme\page_set() ) as $path ) {
			if ( ! get_page_by_path( $path ) ) {
				++$missing;
			}
		}
		$rows[] = array( __( 'Pages', 'hopfly' ), 0 === $missing, 0 === $missing ? __( 'All pages exist', 'hopfly' ) : sprintf( /* translators: %d: count */ __( '%d missing', 'hopfly' ), $missing ) );
	} else {
		$rows[] = array( __( 'Pages', 'hopfly' ), false, __( 'Activate the HopFly Cycling theme first', 'hopfly' ) );
	}
	$teams  = wp_count_terms( array( 'taxonomy' => 'hopfly_team', 'hide_empty' => false ) );
	$rows[] = array( __( 'Sponsor teams', 'hopfly' ), (int) $teams >= count( default_teams() ), sprintf( /* translators: %d: count */ __( '%d teams', 'hopfly' ), (int) $teams ) );
	$rows[] = array( __( 'Sponsors', 'hopfly' ), (int) wp_count_posts( 'hopfly_sponsor' )->publish > 0, (int) wp_count_posts( 'hopfly_sponsor' )->publish . ' ' . __( 'published (add logos and links under Sponsors)', 'hopfly' ) );
	$rows[] = array( __( 'Events', 'hopfly' ), (int) wp_count_posts( 'hopfly_event' )->publish > 0, (int) wp_count_posts( 'hopfly_event' )->publish . ' ' . __( 'published', 'hopfly' ) );
	if ( class_exists( 'GFAPI' ) ) {
		$titles = array();
		foreach ( \GFAPI::get_forms() as $form ) {
			$titles[ (int) $form['id'] ] = $form['title'];
		}
		$ok     = ( $titles[1] ?? '' ) === 'Email signup' && ( $titles[2] ?? '' ) === 'Contact' && ( $titles[3] ?? '' ) === 'Email signup (page band)';
		$rows[] = array( __( 'Gravity Forms', 'hopfly' ), $ok, $ok ? __( 'Forms 1, 2 and 3 are in place', 'hopfly' ) : __( 'The theme expects form 1 = Email signup, 2 = Contact, 3 = Email signup (page band). Run setup on a site with no other forms.', 'hopfly' ) );
	} else {
		$rows[] = array( __( 'Gravity Forms', 'hopfly' ), false, __( 'Install and activate Gravity Forms', 'hopfly' ) );
	}
	if ( taxonomy_exists( 'product_cat' ) ) {
		$have   = count( array_filter( array( 'club-kits', 'rtea', 'support' ), static fn( $s ) => term_exists( $s, 'product_cat' ) ) );
		$rows[] = array( __( 'Shop categories', 'hopfly' ), 3 === $have, $have . ' / 3' );
	} else {
		$rows[] = array( __( 'Shop categories', 'hopfly' ), false, __( 'Install and activate WooCommerce', 'hopfly' ) );
	}
	return $rows;
}

function render_setup_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$result = ! empty( $_GET['done'] ) ? get_transient( 'hopfly_setup_result' ) : null; // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'HopFly Setup', 'hopfly' ); ?></h1>
		<p><?php esc_html_e( 'Creates the starter pages, sponsor teams and names, events, forms and shop categories. It only adds what is missing and never overwrites or deletes anything.', 'hopfly' ); ?></p>
		<?php if ( is_array( $result ) ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( 'Done. Created: ' . implode( ', ', array_map( static fn( $k, $v ) => "$k: $v", array_keys( $result ), $result ) ) ); ?></p></div>
		<?php endif; ?>
		<table class="widefat striped" style="max-width:720px">
			<thead><tr><th><?php esc_html_e( 'Item', 'hopfly' ); ?></th><th><?php esc_html_e( 'Status', 'hopfly' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( setup_status() as $row ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $row[0] ); ?></strong></td>
					<td><span style="color:<?php echo $row[1] ? '#1b7a3d' : '#b3261e'; ?>"><?php echo $row[1] ? '✓' : '✗'; ?></span> <?php echo esc_html( $row[2] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px">
			<input type="hidden" name="action" value="hopfly_setup">
			<?php wp_nonce_field( 'hopfly_setup' ); ?>
			<?php submit_button( __( 'Create anything that is missing', 'hopfly' ) ); ?>
		</form>
	</div>
	<?php
}
