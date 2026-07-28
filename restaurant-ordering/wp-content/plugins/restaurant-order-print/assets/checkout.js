(function ($) {
    'use strict';

    function toggleFields() {
        var type = $('#rop_order_type').val();
        $('.rop-dine-in-field').toggle(type === 'dine-in');
        $('.rop-pickup-field').toggle(type === 'pickup');
    }

    $(document).ready(function () {
        $('#rop_order_type').on('change', toggleFields);
        toggleFields();
    });
})(jQuery);
