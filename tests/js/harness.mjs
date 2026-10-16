/**
 * Runs KilikTable.js in a simulated browser (jsdom), on the HTML rendered by the bundle.
 *
 * The page and the AJAX responses come from the snapshots of the PHP test suite, so the script is tested against
 * the exact markup and JSON produced by the bundle. The jQuery version is selected with JQUERY=jquery3|jquery4.
 */
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

export const jqueryPackage = process.env.JQUERY ?? 'jquery3';

// read from node_modules: jQuery 4 does not export its dist files
const readPackageFile = (path) => readFileSync(new URL(`../../node_modules/${path}`, import.meta.url), 'utf8');
const kilikTableSource = readFileSync(new URL('../../src/Resources/public/js/KilikTable.js', import.meta.url), 'utf8');
const snapshotsDir = new URL('../__snapshots__/', import.meta.url);

export function readSnapshot(path) {
    return readFileSync(new URL(path, snapshotsDir), 'utf8');
}

/**
 * Rebuilds the JSON response from a HandleRequestTest snapshot (scalars, then one section per HTML block).
 */
export function responseFromSnapshot(path) {
    const [scalars, ...blocks] = readSnapshot(path).split(/^===== (\w+) =====\n/m);
    const response = {};
    for (const line of scalars.split('\n').filter(Boolean)) {
        const [key, value] = line.split(': ');
        response[key] = Number(value);
    }
    for (let i = 0; i < blocks.length; i += 2) {
        response[blocks[i]] = blocks[i + 1].replace(/\n$/, '');
    }

    return response;
}

/**
 * Creates a page containing a table rendered by TemplatesTest, with a fake server answering every AJAX request.
 */
export function createPage({
    template = '_defaultTable.html',
    storage = null,
    response = 'HandleRequestTest/template__defaultTable.txt',
    jquery = jqueryPackage,
    migrate = false,
} = {}) {
    const dom = new JSDOM(
        `<!DOCTYPE html><html><head></head><body>${readSnapshot(`TemplatesTest/${template}`)}</body></html>`,
        { runScripts: 'outside-only', url: 'https://example.test/products' },
    );
    const { window } = dom;
    if (null !== storage) {
        window.localStorage.setItem('kilik_table_products', storage);
    }
    window.eval(readPackageFile(`${jquery}/dist/jquery.js`));
    if (migrate) {
        // jQuery Migrate records the use of removed or deprecated APIs in jQuery.migrateMessages
        window.jQuery.migrateMute = true;
        window.jQuery.migrateTrace = false;
        window.eval(readPackageFile('jquery-migrate/dist/jquery-migrate.js'));
    }
    window.eval(kilikTableSource);

    const $ = window.jQuery;
    const json = responseFromSnapshot(response);
    const requests = [];
    $.ajax = (settings) => {
        const request = {
            url: settings.url,
            type: settings.type,
            body: $.param(settings.data),
            fields: Array.from(settings.data, (field) => [field.name, String(field.value)]),
            aborted: false,
        };
        requests.push(request);
        const deferred = $.Deferred();
        const jqXHR = deferred.promise({
            abort() {
                request.aborted = true;
            },
        });
        window.setTimeout(() => {
            if (settings.success) {
                settings.success(json, 'success', jqXHR);
            }
            deferred.resolve(json, 'success', jqXHR);
        }, 0);

        return jqXHR;
    };

    return {
        window,
        $,
        requests,
        response: json,
        options: JSON.parse($('[data-kiliktable-id="products"]').html()),
        migrateMessages: () => Array.from($.migrateMessages ?? []),
        createTable(constructorName = 'KilikTable', options = {}) {
            return new window[constructorName]('products', '/products/list', { ...this.options, ...options });
        },
    };
}

/**
 * Waits for the pending timers of the page (fake AJAX responses, delayed reloads).
 */
export function flush(window, delay = 0) {
    return new Promise((resolve) => window.setTimeout(resolve, delay));
}
