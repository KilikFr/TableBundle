import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createPage, flush } from './harness.mjs';

/**
 * Plays the main interactions with jQuery 4 and jQuery Migrate, which records any removed or deprecated API used.
 */
test('KilikTable.js does not use APIs removed or deprecated in jQuery 4', async () => {
    const page = createPage({
        template: '_defaultTableAlt.html',
        response: 'HandleRequestTest/template__defaultTableAlt.txt',
        storage: JSON.stringify({ rowsPerPage: 20, page: 2, sortColumn: '', sortReverse: false, filters: { 'products_form[p_name]': 'tea' }, hiddenColumns: [] }),
        jquery: 'jquery4',
        migrate: true,
    });
    const { $, window } = page;
    $('form[name="products_form"]').on('submit', (event) => event.preventDefault());
    const table = page.createTable('KilikTableFA', { askForReloadDelay: 0 });

    table.init();
    await flush(window);
    $('th.columnSortable').first().trigger('click');
    await flush(window);
    $('#products_pagination .tablePaginationButton').last().trigger('click');
    await flush(window);
    $('#products_rows_per_page').val('50').trigger('change');
    $('[name="products_form[p_name]"]').val('apple').trigger('keyup').trigger('keydown');
    $('[name="products_form[p_status]"]').val('new').trigger('change');
    await flush(window, 10);
    $('#kilik_products_mass_check').trigger('click');
    $('[data-mass-action][data-name="export"]').trigger('click');
    $('[data-mass-action][data-name="delete"]').trigger('click');
    $('[data-setup="products"]').first().prop('checked', false).trigger('change');
    await flush(window);
    $('.btn-clear-filters').trigger('click');
    await flush(window);

    assert.ok(page.requests.length >= 7);
    assert.deepEqual(page.migrateMessages(), []);
});
