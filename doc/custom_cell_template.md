# Custom Cell Template

## How to use custom cell template on a column ?

This column is rendered with a custom template: 

![image](https://user-images.githubusercontent.com/10455897/127518818-59b57ac5-db70-445d-9623-a1862819cb81.png)

In this example, we want to display a formatted date and the time difference (with a twig filter named "ago").

## How to ?

- use setCellTemplate method (on Column object)
- define a custom template (wich extends or replace @KilikTable/_columnCell.html.twig)

**Controller**

```php
$table->addColumn(
    (new Column())
        ->setLabel('Création')
        // setup custom template for cell (body) rendering
        ->setCellTemplate('application/_column_creation.html.twig') 
        ->setSort(['a.creationDateTime' => 'asc'])
        ->setFilter(
            (new Filter())
                ->setField('a.creationDateTime')
                ->setName('a_creationDateTime')
                ->setDataFormat(Filter::FORMAT_DATE)
        )
);
```

**View**

```twig
{% extends "@KilikTable/_columnCell.html.twig" %}

{# @KilikTable/_columnCell.html.twig #}
{# @param table: Kilik\Table #}
{# @param column: Kilik\Column #}
{# @param row: array (from line result) #}

{% block tableBodyCellInner %}
    {{ table.value(column,row) | date('d/m/Y H:i') }} - {{ table.value(column,row) | ago }}
{% endblock tableBodyCellInner %}
```
