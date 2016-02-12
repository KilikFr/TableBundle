KilikTableBundle is a fast, modern, and easy-to-use way to manipulate paginated 
information, with filtering and ordering features, with ajax queries.

This bundle is a work in progress.

Working features:
- pagination
- basic filtering (like %...%)
- ordering by column (and reverse)
- basic table template extendable
- keep filters and orders in browser local storage (api REST)

Planned features:
- show ordered column (and way)
- max items per page selector (customizable)
- delay on keyup events (to prevent multiple reloads)
- customize visibles colums (hide/show checkboxes)
- clean/reset filters in local storage browser
- add advanced templates
- documentation
- examples
- translations
- loading image waiting ajax response
- new filters (checkbox, date, etc...)
- new column display types (now: only raw text value)
- column display callback
- multiple lists on one page (binding jquery functions on good scopes)

## Example (need bootstrap + jquery)

DefaultController.php:
```php
<?php

namespace AppBundle\Controller;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;

class DefaultController
{

    /**
     * Get KilikTable for contacts
     */
    public function getContactTable()
    {
        $queryBuilder = $this->getDoctrine()->getRepository("AppBundle:Contact")->createQueryBuilder("c")
                ->select('c')
        ;

        $table = (new Table())
                ->setId("contact_list")
                ->setPath($this->generateUrl("contact_list_ajax"))
                ->setQueryBuilder($queryBuilder, "c")
                ->addColumn(
                        (new Column())->setLabel("First Name")
                        ->setSort(["c.firstname"=>"asc"])
                        ->setFilter((new Filter())
                                ->setName("c_firstname")
                                ->setField("c.firstname")
                        )
                )
                ->addColumn(
                        (new Column())->setLabel("Surname")
                        ->setSort(["c.surname"=>"asc"])
                        ->setFilter((new Filter())
                                ->setField("c.surname")
                                ->setName("c_surname")
                        )
                )
                ->addColumn(
                        (new Column())->setLabel("Email")
                        ->setSort(["c.email"=>"asc", "c.firstname"=>"asc", "c.surname"=>"asc"])
                        ->setSortReverse(["c.email"=>"desc", "c.firstname"=>"asc", "c.surname"=>"asc"])
                        ->setFilter((new Filter())
                                ->setField("c.email")
                                ->setName("email")
                        )
                );

        return $table;
    }

    /**
     * @Route("/list", name="contact_list")
     * @Template()
     */
    public function contactListAction()
    {
        return ["table"=>$this->get("kilik_table")->createFormView($this->getContactTable())];
    }

    /**
     * @Route("/list/ajax", name="contact_list_ajax")
     */
    public function _contactListAction(Request $request)
    {
        return $this->get("kilik_table")->handleRequest($this->getContactTable(), $request);
    }

}
```

contactList.html.twig:
```php
{% block content %}

    <div class="panel panel-info">        
        <div class="panel-heading">        
            <div class="panel-title">
                {# pagination #}
                {% include "KilikTableBundle::_pagination.html.twig" %}
                <b>Contacts list</b>
            </div>
        </div>        
        <div class="panel-body">        
            {# table #}
            {% include "KilikTableBundle::_defaultTable.html.twig" %}
        </div>        
    </div>        

{% endblock content %}

{% block javascripts %}
    <script type="text/javascript">
        $(document).ready(function () {
            var table = new KilikTable("{{ table.id }}", "{{ table.path }}");
            table.init();
        });
    </script>

{% endblock javascripts %}
```