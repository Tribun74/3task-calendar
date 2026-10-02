/**
 * 3task Calendar - date panel in the block editor
 *
 * Adds a panel to the post sidebar. The date is stored in post meta, the
 * plugin creates the calendar event from it when the post is saved.
 */

( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.data || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;
	var C = wp.components;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	var cfg = window.threecalPostDates || {};

	if ( ! Panel ) {
		return;
	}

	function DatePanel() {
		var postType = wp.data.useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );
		var meta = wp.data.useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = wp.data.useDispatch( 'core/editor' ).editPost;

		if ( postType !== cfg.postType ) {
			return null;
		}

		var set = function ( key, value ) {
			var change = {};
			change[ key ] = value;
			editPost( { meta: change } );
		};

		var show = meta._threecal_show || '';
		var items = [];

		if ( cfg.source ) {
			// The date comes from an existing custom field.
			items.push( el( 'p', { key: 'date', className: 'threecal-panel-date' }, cfg.mapped ? cfg.mapped : __( 'No date yet.', '3task-calendar' ) ) );
			/* translators: %s: name of the custom field */
			items.push( el( 'p', { key: 'from', className: 'description' }, sprintf( __( 'Taken from the field %s.', '3task-calendar' ), cfg.source ) ) );
			items.push( el( C.ToggleControl, {
				key: 'show',
				label: __( 'Show in the calendar', '3task-calendar' ),
				checked: show !== '0',
				__nextHasNoMarginBottom: true,
				onChange: function ( on ) {
					set( '_threecal_show', on ? '' : '0' );
				}
			} ) );
		} else {
			var start = meta._threecal_start || '';
			var end = meta._threecal_end || '';
			var allDay = meta._threecal_all_day !== '0';
			var date = start.substr( 0, 10 );
			var time = start.length > 10 ? start.substr( 11, 5 ) : '';
			var endDate = end.substr( 0, 10 );
			var endTime = end.length > 10 ? end.substr( 11, 5 ) : '';
			var join = function ( d, t ) {
				if ( ! d ) {
					return '';
				}
				return ( allDay || ! t ) ? d : d + ' ' + t;
			};

			items.push( el( C.ToggleControl, {
				key: 'show',
				label: __( 'Show in the calendar', '3task-calendar' ),
				checked: show === '1',
				__nextHasNoMarginBottom: true,
				onChange: function ( on ) {
					set( '_threecal_show', on ? '1' : '0' );
				}
			} ) );

			if ( show === '1' ) {
				items.push( el( C.TextControl, {
					key: 'date',
					type: 'date',
					label: __( 'Date', '3task-calendar' ),
					value: date,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: function ( value ) {
						set( '_threecal_start', join( value, time ) );
					}
				} ) );
				items.push( el( C.CheckboxControl, {
					key: 'allday',
					label: __( 'All day', '3task-calendar' ),
					checked: allDay,
					__nextHasNoMarginBottom: true,
					onChange: function ( on ) {
						set( '_threecal_all_day', on ? '1' : '0' );
					}
				} ) );
				if ( ! allDay ) {
					items.push( el( C.TextControl, {
						key: 'time',
						type: 'time',
						label: __( 'Time', '3task-calendar' ),
						value: time,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						onChange: function ( value ) {
							set( '_threecal_start', date ? ( value ? date + ' ' + value : date ) : '' );
						}
					} ) );
				}
				items.push( el( C.TextControl, {
					key: 'end',
					type: 'date',
					label: __( 'End (optional)', '3task-calendar' ),
					value: endDate,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
					onChange: function ( value ) {
						set( '_threecal_end', join( value, endTime ) );
					}
				} ) );
				if ( ! allDay && endDate ) {
					items.push( el( C.TextControl, {
						key: 'endtime',
						type: 'time',
						label: __( 'End time', '3task-calendar' ),
						value: endTime,
						__nextHasNoMarginBottom: true,
						__next40pxDefaultSize: true,
						onChange: function ( value ) {
							set( '_threecal_end', value ? endDate + ' ' + value : endDate );
						}
					} ) );
				}
			}
		}

		if ( cfg.category ) {
			/* translators: %s: name of the calendar category */
			items.push( el( 'p', { key: 'cat', className: 'description' }, sprintf( __( 'Calendar category: %s', '3task-calendar' ), cfg.category ) ) );
		}
		if ( cfg.postponed ) {
			items.push( el( 'p', { key: 'postponed', className: 'description' }, cfg.postponed ) );
		}

		return el( Panel, { name: 'threecal-post-date', title: cfg.label, className: 'threecal-post-date-panel' }, el( 'div', { className: 'threecal-panel-fields', style: { display: 'flex', flexDirection: 'column', gap: '12px' } }, items ) );
	}

	wp.plugins.registerPlugin( 'threecal-post-date', { render: DatePanel, icon: null } );
} )( window.wp );
