/**
 * 3task Calendar - Gutenberg Block
 */

(function(blocks, element, components, blockEditor, serverSideRender, i18n) {
    var el = element.createElement;
    var Fragment = element.Fragment;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var SelectControl = components.SelectControl;
    var ServerSideRender = serverSideRender;
    var __ = i18n.__;

    // Register block
    blocks.registerBlockType('threecal/calendar', {
        apiVersion: 3,
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
                default: ''
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var blockProps = useBlockProps({ className: 'threecal-block-preview' });
            var data = window.threecalBlockData || { themes: [], categories: [] };

            var viewOptions = [
                { label: __('Month View', '3task-calendar'), value: 'month' },
                { label: __('List View', '3task-calendar'), value: 'list' },
                { label: __('Poster view (upcoming dates with images)', '3task-calendar'), value: 'poster' }
            ];

            var themeOptions = [{ label: __('Default design (settings)', '3task-calendar'), value: '' }].concat(data.themes || []);
            var categoryOptions = [{ label: __('All Categories', '3task-calendar'), value: 0 }].concat(data.categories || []);

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
                        el(SelectControl, {
                            label: __('View', '3task-calendar'),
                            value: attributes.view,
                            options: viewOptions,
                            onChange: function(value) {
                                props.setAttributes({ view: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Design', '3task-calendar'),
                            value: attributes.theme,
                            options: themeOptions,
                            onChange: function(value) {
                                props.setAttributes({ theme: value });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Category', '3task-calendar'),
                            value: attributes.category,
                            options: categoryOptions,
                            onChange: function(value) {
                                props.setAttributes({ category: parseInt(value, 10) || 0 });
                            }
                        })
                    )
                ),
                el(
                    'div',
                    blockProps,
                    el(ServerSideRender, {
                        block: 'threecal/calendar',
                        attributes: attributes
                    })
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
