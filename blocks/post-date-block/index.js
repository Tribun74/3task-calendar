/**
 * 3task Calendar - block "Date of this post"
 *
 * Shows the date of the post with an "Add to my calendar" button. Works in
 * a single post and inside a query loop.
 */

( function ( blocks, element, components, blockEditor, serverSideRender, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var data = window.threecalBlockData || {};

	blocks.registerBlockType( 'threecal/post-date', {
		apiVersion: 3,
		title: __( 'Date of this post', '3task-calendar' ),
		description: __( 'Shows the date of the post with an "Add to my calendar" button.', '3task-calendar' ),
		icon: 'calendar-alt',
		category: 'widgets',
		keywords: [ __( 'calendar', '3task-calendar' ), __( 'release', '3task-calendar' ), __( 'date', '3task-calendar' ) ],
		usesContext: [ 'postId' ],
		supports: { html: false },
		attributes: {
			theme: { type: 'string', default: '' }
		},

		edit: function ( props ) {
			var postId = props.context && props.context.postId;
			var themes = [ { value: '', label: __( 'Default design', '3task-calendar' ) } ].concat( data.themes || [] );

			return el( 'div', useBlockProps(),
				el( InspectorControls, {},
					el( components.PanelBody, { title: __( 'Settings', '3task-calendar' ) },
						el( components.SelectControl, {
							label: __( 'Design', '3task-calendar' ),
							value: props.attributes.theme,
							options: themes,
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
							onChange: function ( value ) {
								props.setAttributes( { theme: value } );
							}
						} )
					)
				),
				el( serverSideRender, {
					block: 'threecal/post-date',
					attributes: props.attributes,
					urlQueryArgs: postId ? { post_id: postId } : {},
					EmptyResponsePlaceholder: function () {
						return el( 'p', { className: 'threecal-post-date-placeholder' },
							__( 'The date of this post appears here once it is set and the post is published.', '3task-calendar' )
						);
					}
				} )
			);
		},

		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.serverSideRender, window.wp.i18n );
