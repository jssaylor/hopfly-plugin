/**
 * Editor sidebar panels for the Event and Sponsor post types. Plain JavaScript, no build step.
 */
( function ( wp ) {
	var registerPlugin = wp.plugins.registerPlugin;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || wp.editPost.PluginDocumentSettingPanel;
	var useSelect = wp.data.useSelect;
	var useEntityProp = wp.coreData.useEntityProp;
	var c = wp.components;
	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	function useMeta() {
		var type = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );
		var state = useEntityProp( 'postType', type, 'meta' );
		var meta = state[ 0 ] || {};
		var setMeta = state[ 1 ];
		return {
			type: type,
			meta: meta,
			set: function ( key, value ) {
				var next = {};
				next[ key ] = value;
				setMeta( Object.assign( {}, meta, next ) );
			},
		};
	}

	function text( m, key, label, extra ) {
		return el( c.TextControl, Object.assign( { label: label, value: m.meta[ key ] || '', onChange: function ( v ) { m.set( key, v ); }, __nextHasNoMarginBottom: true }, extra || {} ) );
	}

	function Overrides( m ) {
		var rows = [];
		try {
			rows = JSON.parse( m.meta.hopfly_overrides || '[]' ) || [];
		} catch ( e ) {
			rows = [];
		}
		function save( next ) {
			m.set( 'hopfly_overrides', JSON.stringify( next ) );
		}
		function change( i, key, value ) {
			var next = rows.map( function ( r, idx ) {
				if ( idx !== i ) {
					return r;
				}
				var copy = Object.assign( {}, r );
				copy[ key ] = value;
				return copy;
			} );
			save( next );
		}
		return el(
			'div',
			{ className: 'hopfly-overrides' },
			el( 'p', { style: { margin: '0 0 8px', fontWeight: 600 } }, __( 'Date overrides', 'hopfly' ) ),
			el( 'p', { style: { margin: '0 0 12px', color: '#595959' } }, __( 'Cancel or change one Saturday without touching the rest.', 'hopfly' ) ),
			rows.map( function ( r, i ) {
				return el(
					'div',
					{ key: i, style: { border: '1px solid #ddd', borderRadius: 8, padding: 12, marginBottom: 12, display: 'grid', gap: 8 } },
					el( c.TextControl, { type: 'date', label: __( 'Saturday', 'hopfly' ), value: r.date || '', onChange: function ( v ) { change( i, 'date', v ); }, __nextHasNoMarginBottom: true } ),
					el( c.SelectControl, { label: __( 'Status', 'hopfly' ), value: r.status || 'scheduled', options: [ { label: __( 'Scheduled (time change only)', 'hopfly' ), value: 'scheduled' }, { label: __( 'Canceled', 'hopfly' ), value: 'canceled' }, { label: __( 'Postponed', 'hopfly' ), value: 'postponed' } ], onChange: function ( v ) { change( i, 'status', v ); }, __nextHasNoMarginBottom: true } ),
					el( c.TextControl, { type: 'time', label: __( 'Start time that day (optional)', 'hopfly' ), value: r.time || '', onChange: function ( v ) { change( i, 'time', v ); }, __nextHasNoMarginBottom: true } ),
					el( c.TextControl, { label: __( 'Note', 'hopfly' ), value: r.note || '', onChange: function ( v ) { change( i, 'note', v ); }, __nextHasNoMarginBottom: true } ),
					el( c.Button, { variant: 'link', isDestructive: true, onClick: function () { save( rows.filter( function ( x, idx ) { return idx !== i; } ) ); } }, __( 'Remove', 'hopfly' ) )
				);
			} ),
			el( c.Button, { variant: 'secondary', onClick: function () { save( rows.concat( [ { date: '', status: 'canceled', time: '', note: '' } ] ) ); } }, __( 'Add a date override', 'hopfly' ) )
		);
	}

	function EventPanel() {
		var m = useMeta();
		if ( m.type !== 'hopfly_event' ) {
			return null;
		}
		var recurring = m.meta.hopfly_recurrence === 'saturday';
		return el(
			wp.element.Fragment,
			null,
			el(
				Panel,
				{ name: 'hopfly-event-when', title: __( 'When', 'hopfly' ) },
				el( c.__experimentalVStack || 'div', { spacing: 4 },
					el( c.ToggleControl, { label: __( 'Repeats every Saturday', 'hopfly' ), help: __( 'For the Saturday group ride. One start time; use date overrides for changes.', 'hopfly' ), checked: recurring, onChange: function ( v ) { m.set( 'hopfly_recurrence', v ? 'saturday' : '' ); }, __nextHasNoMarginBottom: true } ),
					recurring
						? text( m, 'hopfly_rec_until', __( 'Stop repeating after (optional)', 'hopfly' ), { type: 'date' } )
						: text( m, 'hopfly_start_date', __( 'Start date', 'hopfly' ), { type: 'date' } ),
					recurring ? null : text( m, 'hopfly_end_date', __( 'End date (multi-day events)', 'hopfly' ), { type: 'date' } ),
					el( c.ToggleControl, { label: __( 'All day', 'hopfly' ), checked: !! m.meta.hopfly_all_day, onChange: function ( v ) { m.set( 'hopfly_all_day', v ); }, __nextHasNoMarginBottom: true } ),
					m.meta.hopfly_all_day ? null : text( m, 'hopfly_start_time', __( 'Start time (optional)', 'hopfly' ), { type: 'time' } ),
					m.meta.hopfly_all_day ? null : text( m, 'hopfly_end_time', __( 'End time (optional)', 'hopfly' ), { type: 'time' } ),
					text( m, 'hopfly_date_text', __( 'Date text (optional)', 'hopfly' ), { help: __( 'Replaces the formatted date, for example "September 2027 · dates coming soon".', 'hopfly' ) } )
				)
			),
			recurring ? el( Panel, { name: 'hopfly-event-overrides', title: __( 'Date overrides', 'hopfly' ) }, Overrides( m ) ) : null,
			el(
				Panel,
				{ name: 'hopfly-event-where', title: __( 'Where', 'hopfly' ) },
				el( c.__experimentalVStack || 'div', { spacing: 4 },
					text( m, 'hopfly_venue', __( 'Venue', 'hopfly' ) ),
					text( m, 'hopfly_address', __( 'Address', 'hopfly' ) ),
					text( m, 'hopfly_map_url', __( 'Map link (optional)', 'hopfly' ), { type: 'url', help: __( 'Leave empty to link to a Google Maps search for the venue and address.', 'hopfly' ) } )
				)
			),
			el(
				Panel,
				{ name: 'hopfly-event-registration', title: __( 'Registration & status', 'hopfly' ) },
				el( c.__experimentalVStack || 'div', { spacing: 4 },
					text( m, 'hopfly_reg_url', __( 'Registration link', 'hopfly' ), { type: 'url' } ),
					text( m, 'hopfly_reg_label', __( 'Button label (default: Register)', 'hopfly' ) ),
					el( c.SelectControl, { label: __( 'Status', 'hopfly' ), value: m.meta.hopfly_status || 'scheduled', options: [ { label: __( 'Scheduled', 'hopfly' ), value: 'scheduled' }, { label: __( 'Canceled', 'hopfly' ), value: 'canceled' }, { label: __( 'Postponed', 'hopfly' ), value: 'postponed' } ], onChange: function ( v ) { m.set( 'hopfly_status', v ); }, __nextHasNoMarginBottom: true } ),
					text( m, 'hopfly_status_note', __( 'Status note (optional)', 'hopfly' ), { help: __( 'A short line shown next to the badge, for example "New date to be announced".', 'hopfly' ) } )
				)
			)
		);
	}

	function SponsorPanel() {
		var m = useMeta();
		if ( m.type !== 'hopfly_sponsor' ) {
			return null;
		}
		return el(
			Panel,
			{ name: 'hopfly-sponsor-link', title: __( 'Sponsor link', 'hopfly' ) },
			text( m, 'hopfly_sponsor_url', __( 'Website', 'hopfly' ), { type: 'url', help: __( 'The logo links here. Set the logo as the featured image, and the order (title sponsors first) in the Order field.', 'hopfly' ) } )
		);
	}

	registerPlugin( 'hopfly-panels', {
		render: function () {
			return el( wp.element.Fragment, null, el( EventPanel ), el( SponsorPanel ) );
		},
	} );
} )( window.wp );
