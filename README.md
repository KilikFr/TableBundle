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
- column display callback
- multiple lists on one page
- pre-load default filters and reset local storage filters
- smart filtering on many words (Filter::TYPE_LIKE_WORDS_AND)
- (beta) support api calls to load resources via web services

Planned features:
------------------
- more translations
- add advanced templates
- new column display types (now: only raw text value)

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
