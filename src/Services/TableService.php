<?php

namespace Kilik\TableBundle\Services;

use Doctrine\ORM\QueryBuilder;
use Kilik\TableBundle\Components\TableInterface;
use Symfony\Component\HttpFoundation\Request;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\Filter;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

class TableService extends AbstractTableService
{
    /**
     * @param Table        $table
     * @param Request      $request
     * @param QueryBuilder $queryBuilder
     */
    private function addSearch(Table $table, Request $request, QueryBuilder $queryBuilder)
    {
        $queryParams = $request->get($table->getFormId());

        foreach ($table->getAllFilters() as $filter) {
            if (isset($queryParams[$filter->getName()])) {
                if (is_array($queryParams[$filter->getName()])) {
					$searchParamRaw = array_map('trim', $queryParams[$filter->getName()]);
                    if (is_callable($filter->getQueryPartBuilder())) {
                        $callback = $filter->getQueryPartBuilder();
                        $callback($filter, $table, $queryBuilder, $searchParamRaw);
                    }
                } else {
                    $searchParamRaw = trim($queryParams[$filter->getName()]);
                    if (is_callable($filter->getQueryPartBuilder())) {
                        $callback = $filter->getQueryPartBuilder();
                        $callback($filter, $table, $queryBuilder, $searchParamRaw);
                    } else {
                        list($operator, $searchParam) = $filter->getOperatorAndValue($searchParamRaw);
                        if ((string) $searchParam != '') {
                            list($searchOperator, $formattedSearch) = $filter->getFormattedInput($operator, $searchParam);

                            $sql = false;

                            // depending on operator
                            switch ($searchOperator) {
                                case Filter::TYPE_GREATER:
                                case Filter::TYPE_GREATER_OR_EQUAL:
                                case Filter::TYPE_LESS:
                                case Filter::TYPE_LESS_OR_EQUAL:
                                case Filter::TYPE_NOT_EQUAL:
                                    $sql = $filter->getField()." {$searchOperator} :filter_".$filter->getName();
                                    $queryBuilder->setParameter('filter_'.$filter->getName(), $formattedSearch);
                                    break;
                                case Filter::TYPE_EQUAL_STRICT:
                                    $sql = $filter->getField().' = :filter_'.$filter->getName();
                                    $queryBuilder->setParameter('filter_'.$filter->getName(), $formattedSearch);
                                    break;
                                case Filter::TYPE_EQUAL:
                                    $sql = $filter->getField().' like :filter_'.$filter->getName();
                                    $queryBuilder->setParameter('filter_'.$filter->getName(), $formattedSearch);
                                    break;
                                case Filter::TYPE_NOT_LIKE:
                                    $sql = $filter->getField().' not like :filter_'.$filter->getName();
                                    $queryBuilder->setParameter('filter_'.$filter->getName(), '%'.$formattedSearch.'%');
                                    break;
                                case Filter::TYPE_NULL:
                                    $sql = $filter->getField().' IS NULL';
                                    break;
                                case Filter::TYPE_NOT_NULL:
                                    $sql = $filter->getField().' IS NOT NULL';
                                    break;
                                case Filter::TYPE_IN:
                                    $sql = $filter->getField().' IN (:filter_'.$filter->getName().')';
                                    // $formattedSearch is like 'new,cancelled'
                                    if (is_array($formattedSearch)) {
                                        $values = $formattedSearch;
                                    } else {
                                        $values = explode(',', $formattedSearch);
                                    }
                                    $queryBuilder->setParameter('filter_'.$filter->getName(), $values);
                                    break;
                                case Filter::TYPE_NOT_IN:
                                    $sql = $filter->getField().' NOT IN (:filter_'.$filter->getName().')';
                                    // $formattedSearch is like 'new,cancelled'
                                    if (is_array($formattedSearch)) {
                                        $values = $formattedSearch;
                                    } else {
                                        $values = explode(',', $formattedSearch);
                                    }
                                    $queryBuilder->setParameter('filter_'.$filter->getName(), $values);
                                    break;
                                // when filtering on 'description LIKE WORDS "house red blue"'
                                // results are: description LIKE '%house%' AND
                                case Filter::TYPE_LIKE_WORDS_AND:
                                case Filter::TYPE_LIKE_WORDS_OR:
                                    if ($searchOperator == Filter::TYPE_LIKE_WORDS_OR) {
                                        $binaryOperator = 'OR';
                                    } else {
                                        $binaryOperator = 'AND';
                                    }
                                    $words = [];
                                    foreach (explode(' ', trim($formattedSearch)) as $word) {
                                        // add only non blank words
                                        $word = trim($word);
                                        if ($word) {
                                            $words[] = $word;
                                        }
                                    }
                                    if (count($words) > 0) {
                                        $sql = '(';
                                        foreach ($words as $i => $word) {
                                            if ($i > 0) {
                                                $sql .= ' '.$binaryOperator.' '; // AND / OR
                                            }
                                            $termKey = 'filter_'.$filter->getName().'_t'.$i;
                                            $sql .= $filter->getField().' like :'.$termKey;
                                            $queryBuilder->setParameter($termKey, '%'.$word.'%');
                                        }
                                        $sql .= ')';
                                    }
                                    break;
                                default:
                                case Filter::TYPE_LIKE:
                                    $sql = $filter->getField().' like :filter_'.$filter->getName();
                                    $queryBuilder->setParameter('filter_'.$filter->getName(), '%'.$formattedSearch.'%');
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
        }
    }

    /**
     * Set total rows count without filters.
     *
     * @param Table $table
     */
    protected function setTotalRows(Table $table)
    {
        $qb = $table->getQueryBuilder();
        $qbtr = clone $qb;

        $identifiers = $table->getIdentifierFieldNames();

        if (is_null($identifiers)) {
            $paginatorTotal = new Paginator($qbtr->getQuery());
            $count = $paginatorTotal->count();
        } else {
            $qbtr->select($qbtr->expr()->count($identifiers));
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
     * {@inheritdoc}
     */
    public function getRows(TableInterface $table, Request $request, $paginate = true, $getObjects = true)
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

        // if we need to get objects, LEGACY mode
        if ($getObjects && $table->getEntityLoaderMode() == $table::ENTITY_LOADER_LEGACY) {
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
        if ($getObjects && in_array($table->getEntityLoaderMode(), [$table::ENTITY_LOADER_REPOSITORY, $table::ENTITY_LOADER_CALLBACK])) {
            // create entities identifiers list from scalar rows
            $identifiers = [];
            // results as scalar
            foreach ($rows as $row) {
                // add row identifier to array
                $identifiers[] = $row[$table->getAlias().'_id'];
            }

            // if at least one identifier should be used to load entities
            if (count($identifiers) > 0) {

                // loaded entities
                $entities = $this->loadRowsById($table, $identifiers);

                // associate objects to rows
                if (count($entities) > 0) {
                    foreach ($rows as &$row) {
                        $row['object'] = null;
                        foreach ($entities as $entity) {
                            if ($row[$table->getAlias().'_id'] == $entity->getId()) {
                                $row['object'] = $entity;
                                break;
                            }
                        }
                    }
                }
            }
        }

        // if we need to get objects (legacy mode)
        if ($getObjects && $table->getEntityLoaderMode() == $table::ENTITY_LOADER_LEGACY) {
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
     * @inheritdoc
     */
    public function loadRowsById(TableInterface $table, $identifiers)
    {
        $entities = [];
        // if we need to use repository name
        if ($table->getEntityLoaderMode() == $table::ENTITY_LOADER_REPOSITORY) {
            // if repository name is missing
            if (!$table->getEntityLoaderRepository()) {
                throw new \InvalidArgumentException('entity loader repository name is missing for ENTITY_LOADER_REPOSITORY mode');
            }

            // load entities from identifiers
            $loaderQueryBuilder = $table->getQueryBuilder()
                ->getEntityManager()
                ->getRepository($table->getEntityLoaderRepository())
                ->createQueryBuilder('e')
                ->select('e')
                ->where('e.id IN (:identifiers)')
                ->setParameter('identifiers', $identifiers);

            $entities = $loaderQueryBuilder->getQuery()->getResult();
        } elseif ($table->getEntityLoaderMode() == $table::ENTITY_LOADER_CALLBACK) {
            // if repository callback is missing
            if (!is_callable($table->getEntityLoaderCallback())) {
                throw new \InvalidArgumentException('entity loader callback is missing or not callable for ENTITY_LOADER_CALLBACK mode');
            }
            // else, load entities from callback method
            $callback = $table->getEntityLoaderCallback();
            $entities = $callback($identifiers);
        } else {
            throw new \InvalidArgumentException('unsupported entity loader mode');
        }

        return $entities;
    }
}
