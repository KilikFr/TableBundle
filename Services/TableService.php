<?php

namespace Kilik\TableBundle\Services;

use Twig_Environment;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\Filter;
use Doctrine\ORM\Query;

class TableService
{

    /**
     * Twig Service
     * 
     * @var Twig_Environment
     */
    private $twig;

    /**
     * FormFactory Service
     * 
     * @var FormFactory
     */
    private $formFactory;

    /**
     * Constructeur
     */
    public function __construct(Twig_Environment $twig, FormFactory $formFactory)
    {
        $this->twig        = $twig;
        $this->formFactory = $formFactory;
    }

    /**
     * 
     * @param Table $table
     * @param Request $request
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder
     */
    private function addSearch(Table $table, Request $request, \Doctrine\ORM\QueryBuilder $queryBuilder)
    {
        $queryParams = $request->get($table->getId()."_form");
        // @todo gérer les différents types de filtre
        foreach ($table->getFilters() as $filter) {
            // selon le type de filtre
            switch (false) {
                default:
                    if (isset($queryParams[$filter->getName()])) {
                        $searchParam = $queryParams[$filter->getName()];
                    }
                    else {
                        $searchParam = false;
                    }
                    if ($searchParam != false) {
                        $queryBuilder->andWhere($filter->getField()." like :filter_".$filter->getName());
                        $queryBuilder->setParameter("filter_".$filter->getName(), "%".$searchParam."%");
                    }
                    break;
            }
        }
    }

    /**
     * Get the form (for filtering)
     * 
     * @param Table $table
     * @return 
     */
    public function form(Table $table)
    {
        $form = $this->formFactory->createNamedBuilder($table->getId()."_form");
        //$this->formBuilder->set
        foreach ($table->getFilters() as $filter) {
            // selon le type de filtre
            switch ($filter->getType()) {
                default:
                    $form->add($filter->getName(), \Symfony\Component\Form\Extension\Core\Type\TextType::class);
                    break;
            }
        }

        return $form->getForm()->createView();
    }

    /**
     * Build filter form and get twig params for main view
     * 
     * @param Table $table
     * @return array
     */
    public function createFormView(Table $table)
    {
        return $table->setFormView($this->form($table));
    }

    /**
     * @param Table $table
     * @param Request $request
     * @return Response
     */
    public function handleRequest(Table $table, Request $request)
    {
        $table->setRowsPerPage($request->get("rowsPerPage", 10));
        $table->setPage($request->get("page", 1));

        $qb   = $table->getQueryBuilder();
        // todo: ajouter les contraintes constantes
        // todo: ajouter les contraintes des filtres
        // count total rows
        $qbtr = clone $qb;
        $qbtr->select(" count(distinct {$table->getAlias()}.id) ");
        $table->setTotalRows($qbtr->getQuery()->getSingleScalarResult());

        $qbfr = clone $qb;
        $this->addSearch($table, $request, $qbfr);
        $qbfr->select(" count(distinct {$table->getAlias()}.id) ");
        $table->setFilteredRows($qbfr->getQuery()->getSingleScalarResult());

        $table->setLastPage(ceil($table->getFilteredRows() / $table->getRowsPerPage()));

        if ($table->getPage() > $table->getLastPage()) {
            $table->setPage($table->getLastPage());
        }
        $this->addSearch($table, $request, $qb);
        // todo: changer la limite 
        $qb->setMaxResults($table->getRowsPerPage());
        // todo: pagination, changer le premier
        $qb->setFirstResult(($table->getPage() - 1) * $table->getRowsPerPage());
        $query = $qb->getQuery();
        //$qb->get
        // todo: gérer les filtres
        // todo: gérer la pagination
        //dump($qb);

        $objects = [];
        foreach ($query->getResult(Query::HYDRATE_OBJECT) as $object) {
            $objects[$object->getId()] = $object;
        }
        //dump($objects);
        // in some case, we need scalar results (with OneToMany)
        $scalars = $query->getResult(Query::HYDRATE_SCALAR);
        //dump($scalars);

        $rows = [];
        foreach ($scalars as $key=> $scalar) {
            $rows[] = ["scalar"=>$scalar, "object"=>$objects[$scalar[$table->getAlias()."_id"]]];
            //$rows[] = ["scalar"=>$scalar, "object"=>$objects[$key]];
        }

        $twigParams = [
            "table"=>$table,
            "rows"=>$rows,
        ];

        $template = $this->twig->loadTemplate($table->getTemplate());

        $responseParams = [
            "page"=>$table->getPage(),
            "rowsPerPage"=>$table->getRowsPerPage(),
            "totalRows"=>$table->getTotalRows(),
            "filteredRows"=>$table->getFilteredRows(),
            "lastPage"=>$table->getLastPage(),
            "tableBody"=>$template->renderBlock("tableBody", $twigParams),
            "tableFoot"=>$template->renderBlock("tableFoot", $twigParams),
        ];

        $response = new Response(json_encode($responseParams));

        return $response;
    }

}
