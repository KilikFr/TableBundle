<?php

namespace Kilik\TableBundle\Services;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\TableInterface;
use Kilik\TableBundle\Doctrine\SharedResult;
use Symfony\Component\HttpFoundation\Request;

class TableService extends AbstractTableService
{
    use RequestParameterTrait;

    public const TOTAL_PREFIX = 'TOTAL_';

    /**
     * Whether QueryBuilder::addOrderBy() accepts \SortDirection (Doctrine ORM >= 3.7).
     */
    private static ?bool $sortDirectionSupported = null;

    /**
     * Count computed by setTotalRows(), reused by setFilteredRows() when no filter changes the query.
     *
     * @var array{queryBuilder: \WeakReference<QueryBuilder>, state: array<mixed>, count: int|float}|null
     */
    private ?array $totalRowsCount = null;

    /**
     * {@inheritdoc}
     */
    public function getRows(TableInterface $table, Request $request, $paginate = true, $getObjects = true)
    {
        /** @var Table $table */
        $qb = $this->prepareQueryBuilder($table, $request, $paginate);

        $query = $qb->getQuery();
        $sharedResult = null;

        // if we need to get objects, LEGACY mode
        if ($getObjects && $table->getEntityLoaderMode() == $table::ENTITY_LOADER_LEGACY) {
            // execute the query once: the rows fetched for the objects are hydrated as scalars too
            $sharedResult = SharedResult::share($query);
            if (null !== $qb->getDQLPart('groupBy')) {
                // results as objects
                $objects = [];
                foreach ($query->getResult(Query::HYDRATE_OBJECT) as $object) {
                    if (is_object($object)) {
                        $index = is_int($object->getId()) ? $object->getId() : (string) $object->getId();
                        $objects[$index] = $object;
                    } // when results are mixed with objects and scalar
                    elseif (isset($object[0]) && is_object($object[0])) {
                        $index = is_int($object[0]->getId()) ? $object[0]->getId() : (string) $object[0]->getId();
                        $objects[$index] = $object[0];
                    }
                }
            }
        }

        $rows = $query->getResult(Query::HYDRATE_SCALAR);
        if (null !== $sharedResult) {
            $rows = $sharedResult->cleanScalarRows($rows);
        }

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
                    $entityIndex = new LooseIdentifierIndex($entities);
                    foreach ($rows as &$row) {
                        $row['object'] = $entityIndex->find($row[$table->getAlias().'_id']);
                    }
                }
            }
        }

        // if we need to get objects (legacy mode)
        if ($getObjects && $table->getEntityLoaderMode() == $table::ENTITY_LOADER_LEGACY) {
            // results as scalar
            foreach ($rows as &$row) {
                $index = is_int($row[$table->getAlias().'_id'])
                    ? $row[$table->getAlias().'_id']
                    : (string) $row[$table->getAlias().'_id'];

                if (isset($objects[$index])) {
                    $row['object'] = $objects[$index];
                }
            }
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function loadRowsById(TableInterface $table, $identifiers)
    {
        /** @var Table $table */
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

    /**
     * Set total rows count without filters.
     */
    protected function setTotalRows(Table $table)
    {
        $qb = $table->getQueryBuilder();
        $qbtr = clone $qb;

        $identifiers = $table->getIdentifierFieldNames();
        $count = $this->countRows($qbtr, $identifiers);

        $this->totalRowsCount = ['queryBuilder' => \WeakReference::create($qb), 'state' => self::getQueryBuilderState($qb), 'count' => $count];
        $table->setTotalRows($count);
    }

    /**
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @return float|int
     */
    protected function countRows(QueryBuilder $qb, ?string $identifiers = null)
    {
        switch (true) {
            case $qb->getQuery()->hasHint(Query::HINT_CUSTOM_OUTPUT_WALKER) && null === $identifiers:
                $em = $qb->getEntityManager();
                $sql = $qb->getQuery()->getSQL();
                $rsm = new Query\ResultSetMapping();
                $rsm->addScalarResult('dctrn_count', 'count');
                $nativeQuery = $em->createNativeQuery(sprintf('SELECT COUNT(*) AS dctrn_count FROM (%s) AS dctrn_table', $sql), $rsm);
                foreach ($qb->getParameters() as $key => $item) {
                    $nativeQuery->setParameter($key + 1, $item->getValue());
                }

                return (int) $nativeQuery->getSingleScalarResult();
            case null === $identifiers:
                $paginatorFiltered = new Paginator(self::withoutOrderBy($qb)->getQuery());

                return $paginatorFiltered->count();
            default:
                $qb->select($qb->expr()->count($identifiers));

                return (int) $qb->getQuery()->getSingleScalarResult();
        }
    }

    /**
     * Applies the request to the table and its query builder: pagination, filters, sort, and computes the totals.
     *
     * @param Table $table
     * @param bool  $paginate
     */
    private function prepareQueryBuilder(TableInterface $table, Request $request, $paginate): QueryBuilder
    {
        $table->setRowsPerPage((int) $this->getRequestParameter($request, 'rowsPerPage', 10));
        $table->setPage((int) $this->getRequestParameter($request, 'page', 1));

        foreach ((array) $this->getRequestParameter($request, 'hiddenColumns', []) as $hiddenColumnName => $notUsed) {
            $column = $table->getColumnByName($hiddenColumnName);
            if (null !== $column) {
                $column->setHidden(true);
            }
        }

        $qb = $table->getQueryBuilder();

        if ($paginate) {
            // @todo: had possibility to define custom count queries
            $this->setTotalRows($table);
            $this->setFilteredRows($table, $request);

            // compute last page and floor curent page
            $table->setLastPage((int) ceil($table->getFilteredRows() / $table->getRowsPerPage()));

            if ($table->getPage() > $table->getLastPage()) {
                $table->setPage($table->getLastPage());
            }

            $qb->setMaxResults($table->getRowsPerPage());
            $qb->setFirstResult(($table->getPage() - 1) * $table->getRowsPerPage());
        }

        // add filters
        $this->addSearch($table, $request, $qb);

        // handle ordering
        $queryParams = $this->getRequestParameter($request, $table->getFormId());

        if (isset($queryParams['sortColumn']) && '' != $queryParams['sortColumn']) {
            $column = $table->getColumnByName($queryParams['sortColumn']);
            // if column exists
            if (null !== $column) {
                if (null !== $column->getSort()) {
                    $qb->resetDQLPart('orderBy');
                    if (isset($queryParams['sortReverse'])) {
                        $sortReverse = $queryParams['sortReverse'];
                    } else {
                        $sortReverse = false;
                    }
                    foreach ($column->getAutoSort($sortReverse) as $sortField => $sortOrder) {
                        $qb->addOrderBy($sortField, $this->toOrmSortDirection($sortOrder));
                    }
                }
            }
        }

        // force a final ordering by id
        $qb->addOrderBy($table->getAlias().'.id', $this->toOrmSortDirection('asc'));

        if ($table->haveTotalColumns()) {
            $totalQueryBuilder = clone $qb;
            $totalQueryBuilder->setMaxResults(null)->setFirstResult(null);
            // the totals are read in the first result: with one row per root entity, it is the first row
            if (self::isGroupedByRootEntity($totalQueryBuilder)) {
                $totalQueryBuilder->setMaxResults(1);
            }
            foreach ($table->getColumns() as $column) {
                if ($column->isUseTotal()) {
                    $totalQueryBuilder->addSelect('SUM('.$column->getFilter()->getField().') AS '.self::TOTAL_PREFIX.$column->getName());
                }
            }
            $totalResults = $totalQueryBuilder->getQuery()->getResult()[0] ?? [];
            foreach ($table->getColumns() as $column) {
                $totalColumnResultName = self::TOTAL_PREFIX.$column->getName();
                if ($column->isUseTotal()) {
                    $callback = $column->getDisplayCallback();
                    if (null !== $callback) {
                        if (!is_callable($callback)) {
                            throw new \Exception('displayCallback is not callable');
                        }
                        $column->setTotal($callback($totalResults[$totalColumnResultName]) ?? 0);
                    } else {
                        $column->setTotal($totalResults[$totalColumnResultName] ?? 0);
                    }
                }
            }
        }

        return $qb;
    }

    /**
     * @return bool whether the query builder may have been changed
     */
    private function addSearch(Table $table, Request $request, QueryBuilder $queryBuilder): bool
    {
        $queryParams = $this->getRequestParameter($request, $table->getFormId());
        $searchApplied = false;

        foreach ($table->getAllFilters() as $filter) {
            if (!isset($queryParams[$filter->getName()])) {
                continue;
            }
            // Multiple inputs
            if (is_array($queryParams[$filter->getName()])) {
                $searchParamRaw = array_map('trim', $queryParams[$filter->getName()]);
                if (is_callable($filter->getQueryPartBuilder())) {
                    $callback = $filter->getQueryPartBuilder();
                    $callback($filter, $table, $queryBuilder, $searchParamRaw);
                    $searchApplied = true;
                }
                continue;
            }

            $searchParamRaw = trim($queryParams[$filter->getName()]);

            // query build callback
            if (is_callable($filter->getQueryPartBuilder())) {
                $callback = $filter->getQueryPartBuilder();
                $callback($filter, $table, $queryBuilder, $searchParamRaw);
                $searchApplied = true;
                continue;
            }

            list($operator, $searchParam) = $filter->getOperatorAndValue($searchParamRaw);
            if ('' === (string) $searchParam) {
                continue;
            }

            list($searchOperator, $formattedSearch) = $filter->getFormattedInput($operator, $searchParam);

            $sql = null;
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
                    $values = is_array($formattedSearch) ? $formattedSearch : explode(',', $formattedSearch);
                    $queryBuilder->setParameter('filter_'.$filter->getName(), $values);
                    break;
                case Filter::TYPE_NOT_IN:
                    $sql = $filter->getField().' NOT IN (:filter_'.$filter->getName().')';
                    // $formattedSearch is like 'new,cancelled'
                    $values = is_array($formattedSearch) ? $formattedSearch : explode(',', $formattedSearch);
                    $queryBuilder->setParameter('filter_'.$filter->getName(), $values);
                    break;
                    // when filtering on 'description LIKE WORDS "house red blue"'
                    // results are: description LIKE '%house%' AND
                case Filter::TYPE_LIKE_WORDS_AND:
                case Filter::TYPE_LIKE_WORDS_OR:
                    $binaryOperator = Filter::TYPE_LIKE_WORDS_OR == $searchOperator ? 'OR' : 'AND';
                    $words = array_filter(array_map('trim', explode(' ', trim($formattedSearch))));
                    if (empty($words)) {
                        break;
                    }
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
                    break;
                default:
                case Filter::TYPE_LIKE:
                    $sql = $filter->getField().' like :filter_'.$filter->getName();
                    $queryBuilder->setParameter('filter_'.$filter->getName(), '%'.$formattedSearch.'%');
                    break;
            }

            if (!$sql) {
                continue;
            }

            $filter->getHaving() ? $queryBuilder->andHaving($sql) : $queryBuilder->andWhere($sql);
            $searchApplied = true;
        }

        return $searchApplied;
    }

    /**
     * Set total rows count with filters.
     */
    private function setFilteredRows(Table $table, Request $request)
    {
        $qb = $table->getQueryBuilder();
        $qbfr = clone $qb;
        $searchApplied = $this->addSearch($table, $request, $qbfr);

        // without any filter, the query is the one counted by setTotalRows()
        $totalRowsCount = $this->totalRowsCount;
        $this->totalRowsCount = null;
        if (!$searchApplied
            && null !== $totalRowsCount
            && $totalRowsCount['queryBuilder']->get() === $qb
            && $totalRowsCount['state'] === self::getQueryBuilderState($qb)
        ) {
            $table->setFilteredRows($totalRowsCount['count']);

            return;
        }

        $identifiers = $table->getIdentifierFieldNames();
        $count = $this->countRows($qbfr, $identifiers);

        $table->setFilteredRows($count);
    }

    /**
     * Whether the query is grouped by the identifier of its root entity, and by other fields of this entity only.
     *
     * The query then returns one row per root entity, and the object hydration one result per row, in the same order.
     */
    private static function isGroupedByRootEntity(QueryBuilder $queryBuilder): bool
    {
        $from = $queryBuilder->getDQLPart('from');
        if (1 !== \count($from) || null !== $from[0]->getIndexBy()) {
            return false;
        }

        $metadata = $queryBuilder->getEntityManager()->getClassMetadata($from[0]->getFrom());
        if ($metadata->isIdentifierComposite) {
            return false;
        }

        $rootAlias = $from[0]->getAlias();
        $identifier = $rootAlias.'.'.$metadata->getSingleIdentifierFieldName();
        $groupedByIdentifier = false;
        foreach ($queryBuilder->getDQLPart('groupBy') as $groupBy) {
            foreach ($groupBy->getParts() as $part) {
                foreach (explode(',', (string) $part) as $expression) {
                    $expression = trim($expression);
                    if ($expression === $rootAlias || $expression === $identifier) {
                        $groupedByIdentifier = true;
                    } elseif (1 !== preg_match('/^'.preg_quote($rootAlias, '/').'\.\w+$/', $expression)) {
                        return false;
                    }
                }
            }
        }

        return $groupedByIdentifier;
    }

    /**
     * A copy of the query builder without ORDER BY, needless to count the rows, unless it contains parameters.
     *
     * The paginator output walker keeps the ORDER BY in its subquery, and makes the database sort the rows.
     */
    private static function withoutOrderBy(QueryBuilder $queryBuilder): QueryBuilder
    {
        foreach ($queryBuilder->getDQLPart('orderBy') as $orderBy) {
            if (1 === preg_match('/[:?]/', (string) $orderBy)) {
                return $queryBuilder;
            }
        }

        return (clone $queryBuilder)->resetDQLPart('orderBy');
    }

    /**
     * Everything the count of a query builder depends on.
     *
     * @return array<mixed>
     */
    private static function getQueryBuilderState(QueryBuilder $queryBuilder): array
    {
        $parameters = [];
        foreach ($queryBuilder->getParameters() as $parameter) {
            $parameters[] = [$parameter->getName(), $parameter->getValue(), $parameter->getType()];
        }

        return [$queryBuilder->getDQL(), $parameters, $queryBuilder->getFirstResult(), $queryBuilder->getMaxResults()];
    }

    /**
     * Converts a sort direction to the type expected by the installed Doctrine ORM.
     *
     * Doctrine ORM 3.7 deprecates string directions in favor of \SortDirection, which older versions do not accept
     * (even when symfony/polyfill-php86 provides the enum). "asc" and "desc" are converted case-insensitively, as
     * the ORM does; any other value is passed unchanged.
     *
     * @param string|\SortDirection $order
     *
     * @return string|\SortDirection
     */
    private function toOrmSortDirection($order)
    {
        if (null === self::$sortDirectionSupported) {
            $type = (new \ReflectionMethod(QueryBuilder::class, 'addOrderBy'))->getParameters()[1]->getType();
            self::$sortDirectionSupported = null !== $type && str_contains((string) $type, 'SortDirection');
        }

        if (self::$sortDirectionSupported) {
            if (\is_string($order)) {
                switch (strtolower($order)) {
                    case 'asc':
                        return \SortDirection::Ascending;
                    case 'desc':
                        return \SortDirection::Descending;
                }
            }

            return $order;
        }

        if ($order instanceof \SortDirection) {
            return \SortDirection::Ascending === $order ? 'ASC' : 'DESC';
        }

        return $order;
    }
}
