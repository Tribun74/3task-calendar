/**
 * 3task Calendar - Gutenberg Block
 */

(function(blocks, element, components, blockEditor, serverSideRender, i18n) {
    var el = element.createElement;
    var Fragment = element.Fragment;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    // Register block
    blocks.registerBlockType('3task-calendar/calendar', {
        title: __('3task Calendar', '3task-calendar'),
        description: __('Display an event calendar', '3task-calendar'),
        icon: 'calendar-alt',
        category: 'widgets',
        keywords: [
            __('calendar', '3task-calendar'),
            __('events', '3task-calendar'),
            __('schedule', '3task-calendar')
        ],
        supports: {
            html: false,
            align: ['wide', 'full']
        },
        attributes: {
            view: {
                type: 'string',
                default: 'month'
            },
            category: {
                type: 'number',
                default: 0
            },
            theme: {
                type: 'string',
                default: 'default'
            }
        },

        edit: function(props) {
            var attributes = props.attributes;

            var viewOptions = [
                { label: __('Month View', '3task-calendar'), value: 'month' },
                { label: __('List View', '3task-calendar'), value: 'list' }
            ];

            var themeOptions = [
                { label: __('Default', '3task-calendar'), value: 'default' },
                { label: __('Minimal', '3task-calendar'), value: 'minimal' },
                { label: __('Gradient', '3task-calendar'), value: 'gradient' },
                { label: __('Glassmorphism', '3task-calendar'), value: 'glassmorphism' },
                { label: __('Boxed', '3task-calendar'), value: 'boxed' }
            ];

            return el(
                Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        {
                            title: __('Calendar Settings', '3task-calendar'),
                            initialOpen: true
                        },
                        el(
                            SelectControl,
                            {
                                label: __('View', '3task-calendar'),
                                value: attributes.view,
                                options: viewOptions,
                                onChange: function(value) {
                                    props.setAttributes({ view: value });
                                }
                            }
                        ),
                        el(
                            SelectControl,
                            {
                                label: __('Theme', '3task-calendar'),
                                value: attributes.theme,
                                options: themeOptions,
                                onChange: function(value) {
                                    props.setAttributes({ theme: value });
                                }
                            }
                        )
                    )
                ),
                el(
                    'div',
                    { className: 'threecal-block-preview' },
                    el(
                        'div',
                        { className: 'threecal-block-header' },
                        el('span', { className: 'dashicons dashicons-calendar-alt' }),
                        el('span', null, ' 3task Calendar'),
                        el('span', { className: 'threecal-block-theme' }, ' — ' + attributes.theme)
                    ),
                    el(
                        'div',
                        { className: 'threecal-block-placeholder' },
                        el('p', null, __('Calendar will be displayed here', '3task-calendar')),
                        el('small', null, __('View:', '3task-calendar') + ' ' + attributes.view)
                    )
                )
            );
        },

        save: function() {
            // Server-side render
            return null;
        }
    });

})(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.serverSideRender,
    window.wp.i18n
);
