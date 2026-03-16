<p align="center">
  <strong>KilikTableBundle</strong><br>
  Fast, modern, AJAX-powered datagrid tables for Symfony & Doctrine
</p>

<p align="center">
  <a href="https://packagist.org/packages/kilik/table"><img src="https://img.shields.io/packagist/v/kilik/table.svg" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/kilik/table"><img src="https://img.shields.io/packagist/dt/kilik/table.svg" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/kilik/table"><img src="https://img.shields.io/packagist/l/kilik/table.svg" alt="License"></a>
</p>

<p align="center">
  <a href="http://tabledemo.kilik.fr/">Live Demo</a> &bull;
  <a href="https://github.com/KilikFr/TableDemoBundle">Demo Bundle</a> &bull;
  <a href="#installation">Installation</a> &bull;
  <a href="#usage">Usage</a>
</p>

---

## Features

| Category | Details |
|---|---|
| **Pagination** | Page navigation, configurable rows per page, rows per page selector |
| **Filtering** | LIKE, advanced operators (`<`, `>`, `<=`, `>=`, `=`, `!=`), multi-word search (`LIKE_WORDS_AND`), checkbox & select filters, date filters |
| **Sorting** | Click-to-sort columns, reverse order, visual sort indicators |
| **Export** | CSV export of filtered rows |
| **Mass Actions** | Checkbox selection, bulk actions with PHP callback or JS event |
| **Columns** | Hide/show toggle, custom cell templates, callback display, `data-label` attributes |
| **Responsive** | Opt-in card layout on mobile via `setResponsive(true)` |
| **Storage** | Filters & sort persisted in browser localStorage (or custom storage) |
| **Templates** | Bootstrap 3 and Bootstrap 4 (with Font Awesome) themes |
| **API** | Beta support for external API data sources |
| **Multi-table** | Multiple tables on a single page |

---

## Installation

### 1. Require the package

```sh
composer require kilik/table
```

### 2. Register the bundle

<details>
<summary>Symfony >= 4 (Flex)</summary>

If Flex doesn't register it automatically, add to `config/bundles.php`:

```php
return [
    // ...
    Kilik\TableBundle\KilikTableBundle::class => ['all' => true],
];
```

</details>

<details>
<summary>Symfony < 4</summary>

Add to `app/AppKernel.php`:

```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new \Kilik\TableBundle\KilikTableBundle(),
        ];
    }
}
```

</details>

### 3. Install assets

```sh
./bin/console assets:install --symlink
```

### 4. Generate your first table (optional)

```sh
./bin/console kilik:table:generate
```

> Your list will be available at `http://localhost/yourcontroller/list`

---

## Usage

### Quick start

```php
$table = (new Table())
    ->setId('products')
    ->setPath($this->generateUrl('product_list_ajax'))
    ->setTemplate('@KilikTable/_defaultTable.html.twig')
    ->setEntityLoaderRepository("App:Product")
;
```

### Entity loading

**From repository name:**

```php
$table = (new Table())
    ->setEntityLoaderRepository("App:Product")
;
```

**From callback (eager loading):**

```php
$table = (new Table())
    ->setEntityLoaderCallback(function ($ids) {
        return $this->manager()->getRepository('App:Product')->findById($ids);
    })
;
```

---

### Mass actions

Define a mass action on your table:

```php
$massAction = new MassAction('delete', 'Delete selected items');
// 'delete' is the identifier — no spaces or special characters
$massAction->setAction('path/to/my-form-action.php');

$table = (new Table())
    ->addMassAction($massAction)
;
```

Retrieve selected rows in your controller:

```php
$selectedEntities = $this->get('kilik_table')
    ->getSelectedRows($request, $this->getTable());

foreach ($selectedEntities as $entity) {
    $entity->doSomething();
}
```

**Without a form action**, a JavaScript event is fired instead:

```javascript
$("#table_id").on('kilik:massAction', function (e, detail) {
    if (detail.checked.length === 0) return false;
    if (detail.action === 'delete') {
        // handle delete...
    }
});
```

---

### Events

| Event | When |
|---|---|
| `kilik:init:start` | Table initialization starts |
| `kilik:init:end` | Table initialization ends |
| `kilik:massAction` | A mass action button is clicked |

```javascript
$(document).on('kilik:init:end', function (event, table) {
    // table is fully initialized
});
```

---

### Autoload tables

The metadata block in templates provides everything needed to auto-discover and initialize tables without writing JS in your Twig:

```javascript
var loadKiliktables = function () {
    $("[data-kiliktable-id]").each(function () {
        var $el = $(this);
        var id = $el.data("kiliktable-id");
        if (id.length > 0) {
            var path = $el.data("kiliktable-path");
            var options = JSON.parse($el.html());
            new KilikTableFA(id, path, options).init();
        }
    });
};
```

---

### Bootstrap 4 / Font Awesome

Use `KilikTableFA` instead of `KilikTable` and the dark4 theme template:

```javascript
$(document).ready(function () {
    var table = new KilikTableFA(
        "{{ table.id }}",
        "{{ table.path }}",
        JSON.parse('{{ table.options | json_encode | raw }}')
    );
    table.init();
});
```

```php
$table = (new Table())
    ->setTemplate('@KilikTable/theme/dark4/tables/default.html.twig')
;
```

---

### Responsive mode (mobile cards)

On small screens (< 768px), tables overflow and become unusable. The responsive mode transforms each row into a **stacked card** with labeled fields.

> This feature is **opt-in** — existing tables are not affected.

#### Activation

```php
$table = (new Table())
    ->setId('my_table')
    ->setPath($this->generateUrl('my_table_ajax'))
    ->setResponsive(true) // enable responsive card layout
;
```

No other change is needed. Templates, CSS, and JavaScript handle everything automatically.

#### Behavior comparison

| Element | Desktop (>= 768px) | Mobile (< 768px) |
|---|---|---|
| **Data rows** | Standard `<table>` | Each `<tr>` = card, each `<td>` = labeled flex row |
| **Filters** | Inline in `<thead>` | Hidden by default, toggled via "Filters" button |
| **Sorting** | Click on column headers | Dropdown menu with sortable columns |
| **Mass actions** | Checkbox in thead + tbody | Checkboxes inside each card |
| **Pagination** | Panel header / card header | Same, buttons wrap |
| **Hidden columns** | `jQuery .hide()` | Unchanged (`display:none` inline takes priority) |

#### Custom cell templates

If you use custom cell templates via `column.cellTemplate`, make sure the `<td>` includes the `data-label` attribute for labels to appear in card mode:

```twig
<td data-label="{{ column.label }}" data-column="{{ column.name }}">
    {# your custom content #}
</td>
```

> The default cell template (`_columnCell.html.twig`) already includes this attribute.

#### CSS customization

All responsive styles are scoped under `.kilik-table-responsive`. Override them in your own stylesheet:

```css
@media (max-width: 767.98px) {
    .kilik-table-responsive tbody tr {
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    }
}
```

---

### Date filters

```php
$table->addColumn(
    (new Column())
        ->setSort(['u.createdAt' => 'asc'])
        ->setDisplayFormat(Column::FORMAT_DATE)
        ->setDisplayFormatParams('d/m/Y H:i:s')
        ->setFilter(
            (new FilterDate())
                ->setName('u_createdAt')
                ->setField('u.createdAt')
                ->setInputFormat(FilterDate::INPUT_FORMAT_LITTLE_ENDIAN)
        )
);
```

**Supported operators:**

| Input | Meaning |
|---|---|
| `26/02/1802` or `=26/02/1802` | Exact day |
| `!=21/11/1694` | Any day except this one |
| `>26/02/1802 18:00` | After this date and time |
| `>=02/1802` | February 1802 and after |
| `<2024` | Before 2024 |
| `<=26/02/1802 15` | At or before this date at 3pm |
| `=` | Date is NULL |
| `!=` | Date is not NULL |

---

### Custom filter storage

Replace localStorage with your own storage (e.g. session):

```php
// Disable localStorage for filters
public function getTable()
{
    return (new Table())->setSkipLoadFilterFromLocalStorage(true);
}

// AJAX action: store filters
public function _list(Request $request)
{
    $table = $this->getTable();
    $response = $this->get('kilik_table')->handleRequest($table, $request);

    $this->kilik->createFormView($table);
    $table->getForm()->handleRequest($request);
    $data = $table->getForm()->getData();

    $this->filterStorage->store($data);

    return $response;
}

// Default action: restore filters
public function list()
{
    $table = $this->getTable();
    $data = $this->filterStorage->get();

    return $this->render('list.html.twig', [
        'table' => $this->kilik->createFormView($table, $data),
    ]);
}
```

---

### Customize filled filter style

When a filter has a value, the class `table-filter-filled` is added to the field. Style it as you wish:

```css
.table-filter-filled {
    background-color: #fff3cd;
    border-color: #ffc107;
}
```

---

## Contributing

```sh
# Install dependencies
./prepare-tests.sh

# Run tests
./run-tests.sh

# Composer commands
./scripts/composer.sh <command>
```

---

## License

This bundle is released under the [MIT License](LICENSE).
