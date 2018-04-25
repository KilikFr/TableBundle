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
- loading image waiting ajax response
- new column display types (now: only raw text value)

Installation
------------
```sh
composer require kilik/table
```

Patch your AppKernel.php:
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
