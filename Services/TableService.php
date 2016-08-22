<?php

namespace Kilik\TableBundle\Services;

use Twig_Environment;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\FilterCheckbox;
use Kilik\TableBundle\Components\FilterSelect;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

class TableService
{
    /**
     * Twig Service.
     *
     * @var Twig_Environment
     */
    private $twig;

    /**
     * FormFactory Service.
     *
     * @var FormFactory
     */
    private $formFactory;

    /**
     * TableService constructor.
     *
     * @param Twig_Environment $twig
     * @param FormFactory      $formFactory
     */
    public function __construct(Twig_Environment $twig, FormFactory $formFactory)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
    }

    /**
     * @param Table                      $table
     * @param Request                    $request
     * @param \Doctrine\ORM\QueryBuilder $queryBuilder
     */
    private function addSearch(Table $table, Request $request, \Doctrine\ORM\QueryBuilder $queryBuilder)
    {
        $queryParams = $request->get($table->getFormId());
        // @todo handle all kind of filters
        foreach ($table->getAllFilters() as $filter) {
            if (isset($queryParams[$filter->getName()])) {
                $searchParamRaw = trim($queryParams[$filter->getName()]);
                list($operator, $searchParam) = $filter->getOperatorAndValue($searchParamRaw);
                if ((string) $searchParam != '') {
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
                            $queryBuilder->setParameter('filter_'.$filter->getName(), $formatedSearch);
                            break;
                        case Filter::TYPE_EQUAL_STRICT:
                            $sql = $filter->getField().' = :filter_'.$filter->getName();
                            $queryBuilder->setParameter('filter_'.$filter->getName(), $formatedSearch);
                            break;
                        case Filter::TYPE_EQUAL:
                            $sql = $filter->getField().' like :filter_'.$filter->getName();
                            $queryBuilder->setParameter('filter_'.$filter->getName(), $formatedSearch);
                            break;
                        case Filter::TYPE_NOT_LIKE:
                            $sql = $filter->getField().' not like :filter_'.$filter->getName();
                            $queryBuilder->setParameter('filter_'.$filter->getName(), '%'.$formatedSearch.'%');
                            break;
                        default:
                        case Filter::TYPE_LIKE:
                            $sql = $filter->getField().' like :filter_'.$filter->getName();
                            $queryBuilder->setParameter('filter_'.$filter->getName(), '%'.$formatedSearch.'%');
                            break;
                    }
                    if (!is_null($sql)) {
                        if ($filter->getHaving()) {
                            $queryBuilder->andHaving($sql);
                        } else {
                            $queryBuilder->andWhere($sql);
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the form (for filtering).
     *
     * @param Table $table
     *
     * @return
     */
    public function form(Table $table)
    {
        $form = $this->formFactory->createNamedBuilder($table->getId().'_form');
        //$this->formBuilder->set
        foreach ($table->getAllFilters() as $filter) {
            // selon le type de filtre
            switch ($filter::FILTER_TYPE) {
                case FilterCheckbox::FILTER_TYPE:
                    $form->add($filter->getName(), \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, ['required' => false]);
                    break;
                case FilterSelect::FILTER_TYPE:
                    $form->add($filter->getName(), \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, ['required' => false, 'choices' => $filter->getChoices(), 'placeholder' => $filter->getPlaceholder()]);
                    break;
                case Filter::FILTER_TYPE:
                default:
                    $form->add($filter->getName(), \Symfony\Component\Form\Extension\Core\Type\TextType::class, ['required' => false]);
                    break;
            }
        }

        // append special inputs (used for export csv for exemple)
        $form->add('sortColumn', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, ['required' => false]);
        $form->add('sortReverse', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, ['required' => false]);

        return $form->getForm()->createView();
    }

    /**
     * Build filter form and get twig params for main view.
     *
     * @param Table $table
     *
     * @return array
     */
    public function createFormView(Table $table)
    {
        return $table->setFormView($this->form($table));
    }

    /**
     * Set total rows count without filters.
     *
     * @param Table $table
     */
    private function setTotalRows(Table $table)
    {
        $qb = $table->getQueryBuilder();
        $qbtr = clone $qb;

        $identifiers = $table->getIdentifierFieldNames();

        if (is_null($identifiers)) {
            $paginatorTotal = new Paginator($qbtr->getQuery());
            $count = $paginatorTotal->count();
        } else {
            $qbtr->select($qbtr->expr()->count($identifiers));
            dump($qbtr);
            $count = (int) $qbtr->getQuery()->getSingleScalarResult();
        }

        $table->setTotalRows($count);
    }

    /**
     * Set total rows count with filters.
     *
     * @param Table   $table
     * @param Request $request
     */
    private function setFilteredRows(Table $table, Request $request)
    {
        $qb = $table->getQueryBuilder();
        $qbfr = clone $qb;
        $this->addSearch($table, $request, $qbfr);

        $identifiers = $table->getIdentifierFieldNames();

        if (is_null($identifiers)) {
            $paginatorFiltered = new Paginator($qbfr->getQuery());
            $count = $paginatorFiltered->count();
        } else {
            $qbfr->select($qbfr->expr()->count($identifiers));
            $count = (int) $qbfr->getQuery()->getSingleScalarResult();
        }

        $table->setFilteredRows($count);
    }

    /**
     * Handle the user request and return an array of all elements.
     *
     * @param Table   $table
     * @param Request $request
     * @param bool    $paginate   : limit selections with pagination mecanism
     * @param bool    $getObjetcs : get objects (else, only scalar results)
     *
     * @return Response
     *
     * table attributes are modified (if paginate=true)
     */
    public function getRows(Table $table, Request $request, $paginate = true, $getObjetcs = true)
    {
        $table->setRowsPerPage($request->get('rowsPerPage', 10));
        $table->setPage($request->get('page', 1));

        foreach ($request->get('hiddenColumns', []) as $hiddenColumnName => $notUsed) {
            $column = $table->getColumnByName($hiddenColumnName);
            if (!is_null($column)) {
                $column->setHidden(true);
            }
        }

        $qb = $table->getQueryBuilder();

        if ($paginate) {
            // @todo: had possibility to define custom count queries
            $this->setTotalRows($table);
            $this->setFilteredRows($table, $request);

            // compute last page and floor curent page
            $table->setLastPage(ceil($table->getFilteredRows() / $table->getRowsPerPage()));

            if ($table->getPage() > $table->getLastPage()) {
                $table->setPage($table->getLastPage());
            }

            $qb->setMaxResults($table->getRowsPerPage());
            $qb->setFirstResult(($table->getPage() - 1) * $table->getRowsPerPage());
        }

        // add filters
        $this->addSearch($table, $request, $qb);

        // handle ordering
        $queryParams = $request->get($table->getFormId());

        if (isset($queryParams['sortColumn']) && $queryParams['sortColumn'] != '') {
            $column = $table->getColumnByName($queryParams['sortColumn']);
            // if column exists
            if (!is_null($column)) {
                if (!is_null($column->getSort())) {
                    $qb->resetDQLPart('orderBy');
                    if (isset($queryParams['sortReverse'])) {
                        $sortReverse = $queryParams['sortReverse'];
                    } else {
                        $sortReverse = false;
                    }
                    foreach ($column->getAutoSort($sortReverse) as $sortField => $sortOrder) {
                        $qb->addOrderBy($sortField, $sortOrder);
                    }
                }
            }
        }

        // force a final ordering by id
        $qb->addOrderBy($table->getAlias().'.id', 'asc');
        $query = $qb->getQuery();

        // if we need to get objects
        if ($getObjetcs) {
            // @todo: change the method to get objects from SCALAR selection, in place of a second query....
            if (!is_null($qb->getDQLPart('groupBy'))) {
                // results as objects
                $objects = [];
                foreach ($query->getResult(Query::HYDRATE_OBJECT) as $object) {
                    if (is_object($object)) {
                        $objects[$object->getId()] = $object;
                    } // when results are mixed with objects and scalar
                    elseif (isset($object[0]) && is_object($object[0])) {
                        $objects[$object[0]->getId()] = $object[0];
                    }
                }
            }
        }

        $rows = $query->getResult(Query::HYDRATE_SCALAR);

        // if we need to get objects
        if ($getObjetcs) {
            // results as scalar
            foreach ($rows as &$row) {
                if (isset($objects[$row[$table->getAlias().'_id']])) {
                    $row['object'] = $objects[$row[$table->getAlias().'_id']];
                }
            }
        }

        return $rows;
    }

    /**
     * Export (selection by filters) as a CSV buffer.
     *
     * @param Table   $table
     * @param Request $request
     *
     * @return string
     */
    public function exportAsCsv(Table $table, Request $request)
    {
        // execute query with filters, without pagination, only scalar results
        $rows = $this->getRows($table, $request, false, false);

        $buffer = '';
        // first line: keys
        if (count($rows) > 0) {
            foreach ($table->getColumns() as $column) {
                $buffer .= $column->getName().';';
            }
            $buffer .= "\n";
        }

        foreach ($rows as $row) {
            foreach ($table->getColumns() as $column) {
                $buffer .= $column->getExportValue($row, $rows).';';
            }
            $buffer .= "\n";
        }

        return $buffer;
    }

    /**
     * Handle the user request and return the JSON response (with pagination).
     *
     * @param Table   $table
     * @param Request $request
     *
     * @return Response
     */
    public function handleRequest(Table $table, Request $request)
    {
        // execute query with filters
        $rows = $this->getRows($table, $request);

        // params for twig parts
        $twigParams = [
            'table' => $table,
            'rows' => $rows,
        ];

        $template = $this->twig->loadTemplate($table->getTemplate());

        $responseParams = [
            'page' => $table->getPage(),
            'rowsPerPage' => $table->getRowsPerPage(),
            'totalRows' => $table->getTotalRows(),
            'filteredRows' => $table->getFilteredRows(),
            'lastPage' => $table->getLastPage(),
            'tableBody' => $template->renderBlock('tableBody', array_merge($twigParams, ['tableRenderBody' => true], $table->getTemplateParams())),
            //"tableFoot"=>$template->renderBlock("tableFoot", $twigParams),
            'tableStats' => $template->renderBlock('tableStatsAjax', array_merge($twigParams, ['tableRenderStats' => true])),
            'tablePagination' => $template->renderBlock('tablePaginationAjax', array_merge($twigParams, ['tableRenderPagination' => true])),
        ];

        // encode response
        $response = new Response(json_encode($responseParams));

        return $response;
    }
}
