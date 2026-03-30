/**
 * Donation Form Block — Editor preview renders shortcode server-side.
 */
(function (blocks, element, blockEditor, components) {
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var ToggleControl = components.ToggleControl;
    var ServerSideRender = wp.serverSideRender;

    blocks.registerBlockType('maranatha-giving/donation-form', {
        edit: function (props) {
            var attrs = props.attributes;

            return el('div', { className: props.className },
                el(InspectorControls, {},
                    el(PanelBody, { title: 'Form Settings' },
                        el(TextControl, {
                            label: 'Fund IDs (comma-separated)',
                            value: attrs.funds,
                            onChange: function (val) { props.setAttributes({ funds: val }); },
                        }),
                        el(TextControl, {
                            label: 'Amounts (comma-separated)',
                            value: attrs.amounts,
                            onChange: function (val) { props.setAttributes({ amounts: val }); },
                        }),
                        el(ToggleControl, {
                            label: 'Show Recurring Options',
                            checked: attrs.showRecurring === 'yes',
                            onChange: function (val) { props.setAttributes({ showRecurring: val ? 'yes' : 'no' }); },
                        }),
                        el(TextControl, {
                            label: 'Form ID',
                            value: attrs.formId,
                            onChange: function (val) { props.setAttributes({ formId: val }); },
                        })
                    )
                ),
                el('div', {
                    style: {
                        padding: '30px',
                        textAlign: 'center',
                        background: '#f8f9fa',
                        border: '2px dashed #ddd',
                        borderRadius: '8px',
                    }
                },
                    el('span', { className: 'dashicons dashicons-heart', style: { fontSize: '32px', color: '#2c3e50' } }),
                    el('p', { style: { margin: '10px 0 0', fontWeight: '600' } }, 'Church Giving — Donation Form'),
                    el('p', { style: { color: '#666', fontSize: '13px' } }, 'This block renders the donation form on the frontend.')
                )
            );
        },

        save: function () {
            return null; // Dynamic block rendered server-side.
        },
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components
);
