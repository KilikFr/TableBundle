<?php

namespace Kilik\TableBundle\Services;

use Twig_Environment;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\Filter;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

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
        $queryParams = $request->get($table->getFormId());
        // @todo gérer les différents types de filtre
        foreach ($table->getAllFilters() as $filter) {
            if (isset($queryParams[$filter->getName()])) {
                $searchParamRaw = $queryParams[$filter->getName()];
                list($operator, $searchParam) = $filter->getOperatorAndValue($searchParamRaw);
                dump([$operator, $searchParam]);
                if ($searchParam != false) {
                    $formatedSearch = $filter->getFormatedInput($searchParam);

                    $sql = false;
                    // selon le type de filtre
                    switch ($operator) {
                        case Filter::TYPE_GREATER:
                        case Filter::TYPE_GREATER_OR_EQUAL:
                        case Filter::TYPE_LESS:
                        case Filter::TYPE_LESS_OR_EQUAL:
                        case Filter::TYPE_NOT_EQUAL:
                            $sql = $filter->getField()." {$operator} :filter_".$filter->getName();
                            $queryBuilder->setParameter("filter_".$filter->getName(), $formatedSearch);
                            break;
                        case Filter::TYPE_EQUAL_STRICT:
                            $sql = $filter->getField()." = :filter_".$filter->getName();
                            $queryBuilder->setParameter("filter_".$filter->getName(), $formatedSearch);
                            break;
                        default:
                        case Filter::TYPE_LIKE:
                            $sql = $filter->getField()." like :filter_".$filter->getName();
                            $queryBuilder->setParameter("filter_".$filter->getName(), "%".$formatedSearch."%");
                            break;
                    }
                    if (!is_null($sql)) {
                        if ($filter->getHaving()) {
                            $queryBuilder->andHaving($sql);
                        }
                        else {
                            $queryBuilder->andWhere($sql);
                        }
                    }
                }
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
        foreach ($table->getAllFilters() as $filter) {
            // selon le type de filtre
            switch ($filter->getType()) {
                default:
                    $form->add($filter->getName(), \Symfony\Component\Form\Extension\Core\Type\TextType::class, ["required"=>false]);
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

        $qb = $table->getQueryBuilder();

        // count total rows (without filters)
        $qbtr           = clone $qb;
        $paginatorTotal = new Paginator($qbtr->getQuery());
        $table->setTotalRows($paginatorTotal->count());

        // count filtered rows (with filters this time)
        $qbfr = clone $qb;
        $this->addSearch($table, $request, $qbfr);

        $paginatorFiltered = new Paginator($qbfr->getQuery());
        $table->setFilteredRows($paginatorFiltered->count());

        // compute last page and floor curent page
        $table->setLastPage(ceil($table->getFilteredRows() / $table->getRowsPerPage()));

        if ($table->getPage() > $table->getLastPage()) {
            $table->setPage($table->getLastPage());
        }

        // gets results
        $this->addSearch($table, $request, $qb);
        $qb->setMaxResults($table->getRowsPerPage());
        $qb->setFirstResult(($table->getPage() - 1) * $table->getRowsPerPage());

        // handle ordering
        $sortColumn = $request->get("sortColumn");
        if ($sortColumn != "") {
            $column = $table->getColumnByName($sortColumn);
            // if column exists
            if (!is_null($column)) {
                if (!is_null($column->getSort())) {
                    $qb->resetDQLPart("orderBy");
                    foreach ($column->getAutoSort($request->get("sortReverse")) as $sortField=> $sortOrder) {
                        $qb->addOrderBy($sortField, $sortOrder);
                    }
                }
            }
        }

        $qb->addOrderBy($table->getAlias().".id", "asc");
        $query = $qb->getQuery();

        if (!is_null($qb->getDQLPart("groupBy"))) {
            // results as objects
            $objects = [];
            foreach ($query->getResult(Query::HYDRATE_OBJECT) as $object) {
                if (is_object($object)) {
                    $objects[$object->getId()] = $object;
                }
            }
        }
        $rows = $query->getResult(Query::HYDRATE_SCALAR);

        // results as scalar
        foreach ($rows as &$row) {
            if (isset($objects[$row[$table->getAlias()."_id"]])) {
                $row["object"] = $objects[$row[$table->getAlias()."_id"]];
            }
        }

        // prépare response
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
            "tableBody"=>$template->renderBlock("tableBody", array_merge($twigParams, ["tableRenderBody"=>true])),
            //"tableFoot"=>$template->renderBlock("tableFoot", $twigParams),
            "tableStats"=>$template->renderBlock("tableStatsAjax", array_merge($twigParams, ["tableRenderStats"=>true])),
            "tablePagination"=>$template->renderBlock("tablePaginationAjax", array_merge($twigParams, ["tableRenderPagination"=>true])),
        ];

        // encode response
        $response = new Response(json_encode($responseParams));

        return $response;
    }

}
