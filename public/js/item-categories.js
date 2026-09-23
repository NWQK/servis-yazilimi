(function ($) {
    'use strict';

    function populate(category, selected) {
        var row = category.closest('.location_list');
        var product = row.find('.item_name_select');
        var catalog = JSON.parse(category.attr('data-products') || '[]');
        product.empty().append(new Option(category.val() ? 'Ürün seçin' : 'Önce kategori seçin', ''));
        catalog.filter(function (entry) {
            return String(entry.category) === category.val() && (entry.available || String(entry.id) === String(selected));
        }).forEach(function (entry) {
            product.append(new Option(entry.title, entry.id));
        });
        product.val(selected || '').prop('disabled', !category.val()).prop('required', !!category.val()).trigger('change.select2');
    }

    window.initItemCategories = function (root, reset) {
        $(root).find('.item-category-select').each(function () {
            var category = $(this);
            if (category.attr('data-initialized') && !reset) return;
            var selected = reset ? '' : category.closest('.location_list').find('.item_name_select').val();
            var catalog = JSON.parse(category.attr('data-products') || '[]');
            var current = catalog.find(function (entry) { return String(entry.id) === String(selected); });
            category.val(current ? String(current.category) : '').attr('data-initialized', '1');
            populate(category, selected);
            if (!selected) category.closest('.location_list').find('.quantity, .amount').prop('required', false);
        });
    };

    $(document).on('change.itemCategories', '.item-category-select', function () {
        var category = $(this);
        var row = category.closest('.location_list');
        populate(category, '');
        row.find('.quantity, .amount, .description').val('').prop('required', false);
        row.find('.tax_id').val([]).trigger('change.select2');
    });
    $(document).on('change.itemCategories', '.item_name_select', function () {
        var row = $(this).closest('.location_list');
        row.find('.quantity, .amount').prop('required', !!$(this).val());
        if (!$(this).val()) {
            row.find('.quantity, .amount, .description').val('');
            row.find('.tax_id').val([]).trigger('change.select2');
        }
    });
    $(function () { window.initItemCategories(document); });
})(jQuery);
