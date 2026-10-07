<?php
/**
 * Gravity Forms the theme's patterns expect: id 1 = email signup, id 2 = contact.
 * Defined in code so they can be recreated on staging and production:
 *   wp eval 'HopFly\Plugin\ensure_forms();'
 * Create them in a fresh install so the ids are 1 and 2 (the theme patterns use those ids).
 *
 * @package hopfly
 */

namespace HopFly\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Create the two forms if a form with the same title does not exist. Returns title => id.
 */
function ensure_forms(): array {
	if ( ! class_exists( 'GFAPI' ) ) {
		return array();
	}
	$existing = array();
	foreach ( \GFAPI::get_forms() as $form ) {
		$existing[ $form['title'] ] = (int) $form['id'];
	}
	$result = array();
	foreach ( form_definitions() as $title => $form ) {
		if ( isset( $existing[ $title ] ) ) {
			$result[ $title ] = $existing[ $title ];
			continue;
		}
		$id = \GFAPI::add_form( $form );
		if ( ! is_wp_error( $id ) ) {
			$result[ $title ] = (int) $id;
		}
	}
	return $result;
}

function confirmation( string $message ): array {
	$id = uniqid();
	return array(
		$id => array(
			'id'          => $id,
			'name'        => 'Default Confirmation',
			'isDefault'   => true,
			'type'        => 'message',
			'message'     => $message,
			'event'       => '',
			'disableAutoformat' => false,
			'pageId'      => '',
			'url'         => '',
			'queryString' => '',
		),
	);
}

function form_definitions(): array {
	$base = array(
		'description'         => '',
		'labelPlacement'      => 'top_label',
		'descriptionPlacement' => 'below',
		'enableHoneypot'      => true,
		'enableAnimation'     => false,
		'requireLogin'        => false,
		'useCurrentUserAsAuthor' => true,
	);

	$signup = $base + array(
		'title'         => 'Email signup',
		'button'        => array( 'type' => 'text', 'text' => 'Subscribe', 'imageUrl' => '' ),
		'confirmations' => confirmation( '<strong>You\'re on the list.</strong> Check your inbox to confirm your email.' ),
		'notifications' => array(),
		'fields'        => array(
			\GF_Fields::create(
				array(
					'type'        => 'email',
					'id'          => 1,
					'label'       => 'Email address',
					'isRequired'  => true,
					'placeholder' => 'you@example.com',
					'errorMessage' => 'Enter a valid email address, like name@example.com.',
					'autocompleteAttribute' => 'email',
					'cssClass'    => 'hopfly-field--email',
				)
			),
			\GF_Fields::create(
				array(
					'type'          => 'consent',
					'id'            => 2,
					'label'         => 'Consent',
					'isRequired'    => true,
					'checkboxLabel' => 'I agree to get emails from HopFly Cycling. Unsubscribe anytime.',
					'description'   => 'I consent to email marketing from Over The Edge Cycling Club',
					'errorMessage'  => 'Check the box to agree before subscribing.',
				)
			),
		),
	);

	$contact = $base + array(
		'title'         => 'Contact',
		'button'        => array( 'type' => 'text', 'text' => 'Send message', 'imageUrl' => '' ),
		'confirmations' => confirmation( '<strong>Thanks.</strong> We\'ll get back to you soon.' ),
		'notifications' => array(
			'hopfly_notice' => array(
				'id'       => 'hopfly_notice',
				'name'     => 'Admin notification',
				'event'    => 'form_submission',
				'to'       => '{admin_email}',
				'toType'   => 'email',
				'subject'  => 'HopFly contact form: {Topic:3} {Team:5}',
				'message'  => '{all_fields}',
				'from'     => '{admin_email}',
				'fromName' => 'HopFly Cycling website',
				'replyTo'  => '{Email:2}',
				'disableAutoformat' => false,
			),
		),
		'fields'        => array(
			\GF_Fields::create( array( 'type' => 'text', 'id' => 1, 'label' => 'Name', 'isRequired' => true, 'autocompleteAttribute' => 'name' ) ),
			\GF_Fields::create( array( 'type' => 'email', 'id' => 2, 'label' => 'Email', 'isRequired' => true, 'errorMessage' => 'Enter a valid email address, like name@example.com.', 'autocompleteAttribute' => 'email' ) ),
			\GF_Fields::create(
				array(
					'type'        => 'select',
					'id'          => 3,
					'label'       => 'Topic (optional)',
					'isRequired'  => false,
					'placeholder' => 'Choose a topic',
					'choices'     => array_map(
						static fn( string $t ): array => array( 'text' => $t, 'value' => $t, 'isSelected' => false ),
						array( 'A ride', 'Charity Teams', 'Race Teams', 'The shop', 'Sponsoring HopFly', 'Something else' )
					),
				)
			),
			\GF_Fields::create( array( 'type' => 'textarea', 'id' => 4, 'label' => 'Message', 'isRequired' => true, 'size' => 'large' ) ),
			\GF_Fields::create(
				array(
					'type'              => 'hidden',
					'id'                => 5,
					'label'             => 'Team',
					'allowsPrepopulate' => true,
					'inputName'         => 'team',
					'adminLabel'        => 'Team',
				)
			),
		),
	);

	return array( 'Email signup' => $signup, 'Contact' => $contact );
}
