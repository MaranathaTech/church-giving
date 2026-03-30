/**
 * Donor Portal Block — Phase 4
 */
(function (blocks, element) {
    var el = element.createElement;

    blocks.registerBlockType('maranatha-giving/donor-portal', {
        edit: function (props) {
            return el('div', {
                className: props.className,
                style: {
                    padding: '30px',
                    textAlign: 'center',
                    background: '#f8f9fa',
                    border: '2px dashed #ddd',
                    borderRadius: '8px',
                }
            },
                el('span', { className: 'dashicons dashicons-admin-users', style: { fontSize: '32px', color: '#2c3e50' } }),
                el('p', { style: { margin: '10px 0 0', fontWeight: '600' } }, 'Church Giving — Donor Portal'),
                el('p', { style: { color: '#666', fontSize: '13px' } }, 'This block renders the donor portal on the frontend.')
            );
        },
        save: function () {
            return null;
        },
    });
})(window.wp.blocks, window.wp.element);
