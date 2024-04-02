README
======

What's KilikTableBundle ?
--------------------------
KilikTableBundle is a fast, modern, and easy-to-use way to manipulate paginated 
information, with filtering and ordering features, with ajax queries.

This bundle is a work in progress.

Links:
------
- [Live demo](http://tabledemo.kilik.fr/)
- [KilikTableDemoBundle](https://github.com/KilikFr/TableDemoBundle)

Working features:
-----------------
- pagination
- basic filtering (like %...%)
- advanced filtering (<,>,<=,>=,=,!,!=)
- ordering by column (and reverse)
- basic table template extendable
- keep filters and orders in browser local storage (api REST)
- filtering on queries with group by
- show ordered column (normal and reverse)
- max items per page selector (customizable)
- delay on keyup events (to prevent multiple reloads)
- checkbox and select filter
- CSV export of filtered rows
- customization of visible columns (hide/show checkboxes)
- column display colum cells with callback
- [custom display colum cells with template](doc/custom_cell_template.md)
- multiple lists on one page
- pre-load default filters and reset local storage filters
- smart filtering on many words (Filter::TYPE_LIKE_WORDS_AND)
- (beta) support api calls to load resources via web services

Planned features:
------------------
- more translations
- add advanced templates

Installation
------------
```sh
composer require kilik/table
```

Patch your AppKernel.php (symfony <4):
```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new \Kilik\TableBundle\KilikTableBundle(),
        ];
        
        // ...
    }
}    
```

Patch your AppKernel.php (symfony >=4):

```php
<?php

return [
    Kilik\TableBundle\KilikTableBundle::class => ['all' => true],
];
```


Install assets
```sh
./bin/console assets:install --symlink
```

And create your first list: 

Feature disabled on 1.0 branch (symfony 4 compatibility WIP)

```sh
./bin/console kilik:table:generate
```

(With default parameters, your list is available here http://localhost/yourcontroller/list)

Usage
-----

This documentation need to be completed.

Here, some examples to show latest features.

Optimized version to load entities, from Repository Name:

```php
$table = (new Table())
    // ...
    ->setEntityLoaderRepository("KilikDemoBundle:Product")
    // ...
```

Optimized version to load entities, from Callback method (Eager loading):

```php
$table = (new Table())
    // ...
    ->setEntityLoaderCallback(function($ids) {
        return $this->manager()->getRepository('KilikDemoBundle:Product')->findById($ids);
    })
// ...
```

### Mass actions

Define a mass action for list

```php

$massAction = new MassAction('delete', 'Delete selected items'); 
// First parameter 'delete' must not contain space or special characters (identifier)
$massAction->setAction('path/to/my-form-action.php');

$table = (new Table())
    // ...
    ->addMassAction($massAction)
    // ...
    
// Then your form action, you can grab selected rows as entities
$selectedEntities = $this->get('kilik_table')
    ->getSelectedRows($request, $this->getTable());

foreach ($selectedEntities as $entity) {
    // ...
    $entity->doSomething();
    // ...
}
```

If mass action does not have a specified action, a javascript event is fired.
You can get all rows checked as following :

```javascript
 $("#table_id").on('kilik:massAction', function (e, detail) {
    if (detail.checked.length === 0) return false;
    if (detail.action === 'delete') {
        //...
    }
});
```

### Events / Listeners

* `kilik:init:start` jQuery event when table init process starts

```javascript
$(document).on('kilik:init:start', function(event, table) {
    // Do something with event or table object
});
```

* `kilik:init:end` jQuery event when table init process ends

```javascript
$(document).on('kilik:init:start', function(event, table) {
    // Do something with event or table object
});
```

### Autoload Kilik Tables

A new twig block provide metadata information about table so you can autoload it if necessary without any javascript in your twig template.

```html
{% block tableMetadata %}
    <div style="display:none;width:0; height:0;" data-kiliktable-id="{{ table.id }}" data-kiliktable-path="{{ table.path }}">{{ table.options | json_encode | raw }}</div>
{% endblock tableMetadata %}
```

You can access table configurations from HTML attributes with jQuery, see the example :

```javascript
var loadKiliktables = function() {
    var $kilikTables = $("[data-kiliktable-id]");
    if ($kilikTables && $kilikTables.length > 0) {
        $kilikTables.each(function(index, currentTable){
            var $currentTable = $(currentTable);
            var id = $currentTable.data("kiliktable-id");
            if (id.length > 0) {
                var path = $currentTable.data("kiliktable-path");
                var options = $currentTable.html();
                new KilikTableFA(id, path, JSON.parse(options)).init();
            }
        });
    }
}
```

### Bootstrap 4

Note: WIP on Bootstrap 4 (with Font Awesome) integration, use new JS function:

```javascript
$(document).ready(function () {
    var table = new KilikTableFA("{{ table.id }}", "{{ table.path }}", JSON.parse('{{ table.options | json_encode |raw }}'));
    table.init();
});
```

### Use other storage for table filters

If you want to use a custom storage for table filters (Eg. Session).

```php
// Disable using javascript local storage form filters
public function getTable()
{
    return (new Table())->setSkipLoadFilterFromLocalStorage(true);
}

// On ajax action : store filters data
public function _list(Request $request)
{
    $table = $this->getTable();
    $response = $this->get('kilik_table')->handleRequest($table, $request);
    
    // Handle request for table form
    $this->kilik->createFormView($table);
    $table->getForm()->handleRequest($request);
    $data = $table->getForm()->getData();
    
    $this->filterStorage->store($data); // Use your custom storage

    return $response;
}


// On default action
public function list()
{
    $table = $this->getTable();
    $data = $this->filterStorage->get();

    return $this->render('list.html.twig', array(
        'table' => $this->kilik->createFormView($table, $data),
    ));
}

```

### Customize filled filters

When a filter is filled, class <em>table-filter-filled</em> is added on field. By default, no style is applied, but you can override it to fit your needs :

```css
.table-filter-filled {
    ...
}
```

### Filter date columns

```php
$table
    ->addColumn(
        (new Column())
            ->setSort(['u.createdAt' => 'asc'])
            ->setDisplayFormat(Column::FORMAT_DATE)
            ->setDisplayFormatParams('d/m/Y H:i:s') // or for example FilterDate::INPUT_FORMAT_LITTLE_ENDIAN
            ->setFilter((new FilterDate())
                ->setName('u_createdAt')
                ->setField('u.createdAt')
                ->setInputFormat(FilterDate::INPUT_FORMAT_LITTLE_ENDIAN)
            )
    )
;
```

Users can filter this data using various operators, for example :
- `26/02/1802` or `=26/02/1802` : expects a specific day
- `!=21/11/1694` : expects any day except 21 November 1694
- `>26/02/1802 18:00` : expects specific day after 18:00 and without end limit
- `>=02/1802` : expects in february 1802 and after
- `<2024` : expects in 2023 and before
- `<=26/02/1802 15` : expects 26 February 1802 at 3pm or earlier
- `=` : expects date is NULL
- `!=` : expects date is not NULL


For bundle developpers
======================

```shell
# prepare tests
./prepare-tests.sh

# run tests
./run-tests.sh

# launch composer
./scripts/composer.sh
```
