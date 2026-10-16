import { describe, test } from 'node:test';
import assert from 'node:assert/strict';
import { createPage, flush, jqueryPackage } from './harness.mjs';

const ALT = { template: '_defaultTableAlt.html', response: 'HandleRequestTest/template__defaultTableAlt.txt' };

async function initTable(page, constructorName = 'KilikTable', options = {}) {
    const table = page.createTable(constructorName, options);
    table.init();
    await flush(page.window);

    return table;
}

function lastRequest(page) {
    return page.requests[page.requests.length - 1];
}

describe(`KilikTable.js with ${jqueryPackage}`, () => {
    test('init triggers kilik:init:start and kilik:init:end with the table', async () => {
        const page = createPage();
        const events = [];
        page.$(page.window.document).on('kilik:init:start kilik:init:end', (event, table) => events.push([event.type, event.target.id, table]));

        const table = await initTable(page);

        assert.deepEqual(events, [['kilik:init:start', 'products', table], ['kilik:init:end', 'products', table]]);
        assert.equal(page.requests.length, 1);
    });

    test('the first load posts the form, the pagination and the hidden columns', async () => {
        const page = createPage();

        await initTable(page);

        const request = page.requests[0];
        assert.equal(request.url, '/products/list');
        assert.equal(request.type, 'post');
        assert.deepEqual(request.fields, [
            ['products_form[p_name]', 'apple'],
            ['products_form[p_status]', ''],
            ['products_form[p_createdAt]', ''],
            ['products_form[search]', ''],
            ['products_form[sortColumn]', ''],
            ['products_form[sortReverse]', ''],
            ['page', '1'],
            ['rowsPerPage', '20'],
            ['hiddenColumns[p_createdAt]', '1'],
        ]);
        assert.equal(
            request.body,
            'products_form%5Bp_name%5D=apple&products_form%5Bp_status%5D=&products_form%5Bp_createdAt%5D=&products_form%5Bsearch%5D='
            + '&products_form%5BsortColumn%5D=&products_form%5BsortReverse%5D=&page=1&rowsPerPage=20&hiddenColumns%5Bp_createdAt%5D=1',
        );
    });

    test('the response is inserted in the page and updates the table state', async () => {
        const page = createPage();

        const table = await initTable(page);

        const { $, response } = page;
        const text = (html) => $('<div>').html(html).text();
        assert.equal($('#products_body').text(), text(response.tableBody));
        assert.equal($('#products_stats').text(), text(response.tableStats));
        assert.equal($('#products_pagination').text(), text(response.tablePagination));
        assert.equal($('#products_body tr').length, 3);
        assert.deepEqual(
            [table.page, table.lastPage, table.totalRows, table.totalFilteredRows],
            [response.page, response.lastPage, response.totalRows, response.filteredRows],
        );
        assert.ok($('[name="products_form[p_name]"]').hasClass('table-filter-filled'));
        assert.ok(!$('[name="products_form[p_status]"]').hasClass('table-filter-filled'));
    });

    test('the state is saved in localStorage', async () => {
        const page = createPage();

        await initTable(page);

        assert.equal(
            page.window.localStorage.getItem('kilik_table_products'),
            '{"rowsPerPage":20,"page":1,"sortColumn":"","sortReverse":false,"filters":{"products_form[p_name]":"apple",'
            + '"products_form[p_status]":"","products_form[p_createdAt]":"","products_form[search]":"",'
            + '"products_form[sortColumn]":"","products_form[sortReverse]":""},"hiddenColumns":["p_createdAt"]}',
        );
    });

    test('the state is restored from localStorage', async () => {
        const page = createPage({
            ...ALT,
            storage: JSON.stringify({
                rowsPerPage: 50,
                page: 3,
                sortColumn: 'p_name',
                sortReverse: true,
                filters: {
                    'products_form[p_name]': 'tea',
                    'products_form[p_status]': 'new',
                    'products_form[search]': 'organic',
                    'products_form[sortColumn]': 'p_name',
                    'products_form[sortReverse]': '1',
                },
                hiddenColumns: ['p_description'],
            }),
        });

        const table = await initTable(page);

        assert.deepEqual(page.requests[0].fields, [
            ['products_form[p_name]', 'tea'],
            ['products_form[p_status]', 'new'],
            ['products_form[p_createdAt]', ''],
            ['products_form[search]', 'organic'],
            ['products_form[sortColumn]', 'p_name'],
            ['products_form[sortReverse]', '1'],
            ['page', '3'],
            ['rowsPerPage', '50'],
            ['hiddenColumns[p_description]', '1'],
        ]);
        assert.deepEqual([table.sortColumn, table.sortReverse], ['p_name', true]);
        assert.equal(page.$('#products_rows_per_page').val(), '50');
        assert.equal(page.$('th[data-column="p_description"]').css('display'), 'none');
    });

    test('skipLoadFromLocalStorage ignores the stored state, and the default hidden columns too', async () => {
        const page = createPage({ storage: JSON.stringify({ rowsPerPage: 50, page: 3, filters: {}, hiddenColumns: ['p_name'] }) });

        const table = await initTable(page, 'KilikTable', { skipLoadFromLocalStorage: true });

        assert.deepEqual(page.requests[0].fields.slice(-2), [['page', '1'], ['rowsPerPage', '20']]);
        assert.deepEqual(Array.from(table.hiddenColumns), []);
    });

    test('clicking a sortable column sorts, then reverses the sort', async () => {
        const page = createPage();
        const table = await initTable(page);
        const { $ } = page;

        $('th.columnSortable[data-sort-column="p_name"]').trigger('click');
        await flush(page.window);
        assert.deepEqual(lastRequest(page).fields.slice(4, 6), [['products_form[sortColumn]', 'p_name'], ['products_form[sortReverse]', '0']]);
        assert.ok($('th[data-sort-column="p_name"] .columnSortableIcon').hasClass('glyphicon-sort-by-alphabet'));
        assert.ok($('th[data-sort-column="p_id"] .columnSortableIcon').hasClass('glyphicon-sort'));

        $('th.columnSortable[data-sort-column="p_name"]').trigger('click');
        await flush(page.window);
        assert.deepEqual(lastRequest(page).fields.slice(4, 6), [['products_form[sortColumn]', 'p_name'], ['products_form[sortReverse]', '1']]);
        assert.ok($('th[data-sort-column="p_name"] .columnSortableIcon').hasClass('glyphicon-sort-by-alphabet-alt'));
        assert.deepEqual([table.sortColumn, table.sortReverse], ['p_name', true]);
    });

    test('pagination buttons load the requested page', async () => {
        const page = createPage();
        await initTable(page);

        page.$('#products_pagination .tablePaginationButton[data-table-page="3"]').first().trigger('click');
        await flush(page.window);

        assert.equal(page.requests.length, 2);
        assert.deepEqual(lastRequest(page).fields.find(([name]) => 'page' === name), ['page', '3']);
    });

    test('the rows per page selector reloads the table', async () => {
        const page = createPage(ALT);
        await initTable(page);

        page.$('#products_rows_per_page').val('50').trigger('change');
        await flush(page.window);

        assert.deepEqual(lastRequest(page).fields.find(([name]) => 'rowsPerPage' === name), ['rowsPerPage', '50']);
    });

    test('typing in a filter reloads once after the delay', async () => {
        const page = createPage();
        await initTable(page, 'KilikTable', { askForReloadDelay: 20 });
        const input = page.$('[name="products_form[p_name]"]');

        input.val('tea').trigger('keyup');
        input.val('teap').trigger('keyup');
        assert.equal(page.requests.length, 1);
        await flush(page.window, 40);

        assert.equal(page.requests.length, 2);
        assert.deepEqual(lastRequest(page).fields[0], ['products_form[p_name]', 'teap']);
    });

    test('changing a select filter reloads after the delay', async () => {
        const page = createPage();
        await initTable(page, 'KilikTable', { askForReloadDelay: 0 });

        page.$('[name="products_form[p_status]"]').val('validated').trigger('change');
        await flush(page.window, 10);

        assert.deepEqual(lastRequest(page).fields[1], ['products_form[p_status]', 'validated']);
    });

    test('a mass action without URL triggers kilik:massAction with the checked rows', async () => {
        const page = createPage(ALT);
        await initTable(page);
        const { $ } = page;
        const details = [];
        $('#products').on('kilik:massAction', (event, detail) => details.push(detail));
        const checkboxes = $('[name="kilik_products_selected[]"]');
        assert.equal(checkboxes.length, 3);
        checkboxes.slice(0, 2).prop('checked', true);

        $('[data-mass-action][data-name="export"]').trigger('click');

        assert.deepEqual(JSON.parse(JSON.stringify(details)), [{ checked: [checkboxes.eq(0).val(), checkboxes.eq(1).val()], action: 'export' }]);
    });

    test('mass action handlers are bound again on each reload', async () => {
        const page = createPage(ALT);
        const table = await initTable(page);
        table.doReload();
        await flush(page.window);
        const { $ } = page;
        let events = 0;
        $('#products').on('kilik:massAction', () => events++);

        $('[data-mass-action][data-name="export"]').trigger('click');

        assert.equal(events, 2);
    });

    test('a mass action with an URL submits the form to it', async () => {
        const page = createPage(ALT);
        await initTable(page);
        const { $ } = page;
        let action = null;
        $('form[name="products_form"]').on('submit', function (event) {
            event.preventDefault();
            action = $(this).attr('action');
        });

        $('[data-mass-action][data-name="delete"]').trigger('click');

        assert.equal(action, '/products/delete');
    });

    test('the check all box checks every row', async () => {
        const page = createPage(ALT);
        await initTable(page);
        const { $ } = page;

        $('#kilik_products_mass_check').prop('checked', false).trigger('click');

        assert.equal($('#kilik_products_mass_check').prop('checked'), true);
        assert.equal($('[name="kilik_products_selected[]"]:checked').length, 3);
    });

    test('columns can be hidden and shown from the setup menu', async () => {
        const page = createPage(ALT);
        await initTable(page);
        const { $ } = page;
        const setup = (column) => $(`[data-setup="products"][data-column="${column}"]`);
        assert.equal(setup('p_createdAt').prop('checked'), false);
        assert.equal(setup('p_name').prop('checked'), true);

        setup('p_name').prop('checked', false).trigger('change');
        await flush(page.window);
        assert.deepEqual(lastRequest(page).fields.slice(-2), [['hiddenColumns[p_createdAt]', '1'], ['hiddenColumns[p_name]', '1']]);
        assert.equal($('th[data-column="p_name"]').css('display'), 'none');
        assert.equal($('[name="products_form[p_name]"]').val(), '');

        setup('p_name').prop('checked', true).trigger('change');
        await flush(page.window);
        assert.deepEqual(lastRequest(page).fields.slice(-1), [['hiddenColumns[p_createdAt]', '1']]);
        assert.notEqual($('th[data-column="p_name"]').css('display'), 'none');
    });

    test('the clear filters button empties the filled filters', async () => {
        const page = createPage(ALT);
        await initTable(page);

        page.$('.btn-clear-filters').trigger('click');
        await flush(page.window);

        assert.deepEqual(lastRequest(page).fields[0], ['products_form[p_name]', '']);
    });

    test('enabled filters are counted from the filled class, whatever their current value', async () => {
        const page = createPage();
        const table = await initTable(page);

        assert.equal(table.countEnabledFilters(), 1);
        page.$('[name="products_form[p_name]"]').val('');
        assert.equal(table.countEnabledFilters(), 1);
    });

    test('KilikTableFA uses Font Awesome icons', async () => {
        const page = createPage();

        const table = await initTable(page, 'KilikTableFA');

        assert.deepEqual(
            [table.sortColumnClassSortable, table.sortColumnClassSorted, table.sortColumnClassSortedReverse],
            ['fa-sort', 'fa-sort-up', 'fa-sort-down'],
        );
        assert.ok(page.$('th[data-sort-column="p_name"] .columnSortableIcon').hasClass('fa-sort'));
    });
});
