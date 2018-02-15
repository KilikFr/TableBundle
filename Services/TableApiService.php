<?php

namespace Kilik\TableBundle\Services;

use Kilik\TableBundle\Components\ApiTable;
use Kilik\TableBundle\Components\TableInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kilik Table Api Service - to handle.
 */
class TableApiService extends AbstractTableService
{
    /**
     * Parse filters.
     *
     * @param TableInterface $table
     * @param Request        $request
     *
     * @return array
     */
    private function parseFilters(TableInterface $table, Request $request)
    {
        $filters = [];

        $queryParams = $request->get($table->getFormId());

        foreach ($table->getAllFilters() as $filter) {
            if (isset($queryParams[$filter->getName()])) {
                $searchParamRaw = trim($queryParams[$filter->getName()]);
                if ($searchParamRaw != '') {
                    $filters[$filter->getName()] = $searchParamRaw;
                }
            }
        }

        return $filters;
    }

    /**
     * Parse OrderBy.
     *
     * @param TableInterface $table
     * @param Request        $request
     *
     * @return array
     */
    private function parseOrderBy(TableInterface $table, Request $request)
    {
        $orderBy = [];

        $queryParams = $request->get($table->getFormId());

        if (isset($queryParams['sortColumn']) && $queryParams['sortColumn'] != '') {
            $column = $table->getColumnByName($queryParams['sortColumn']);
            // if column exists
            if (!is_null($column)) {
                if (!is_null($column->getSort())) {
                    if (isset($queryParams['sortReverse'])) {
                        $sortReverse = $queryParams['sortReverse'];
                    } else {
                        $sortReverse = false;
                    }
                    foreach ($column->getAutoSort($sortReverse) as $sortField => $sortOrder) {
                        $orderBy[$sortField] = $sortOrder;
                    }
                }
            }
        }

        return $orderBy;
    }

    /**
     * {@inheritdoc}
     */
    public function getRows(TableInterface $table, Request $request, $paginate = true, $getObjects = true)
    {
        /* @var ApiTable $table */
        $table->setRowsPerPage($request->get('rowsPerPage', 10));
        $table->setPage($request->get('page', 1));

        foreach ($request->get('hiddenColumns', []) as $hiddenColumnName => $notUsed) {
            $column = $table->getColumnByName($hiddenColumnName);
            if (!is_null($column)) {
                $column->setHidden(true);
            }
        }

        // get results with api
        $apiResult = $table->getApi()->load(
            $table,
            $this->parseFilters($table, $request),
            $this->parseOrderBy($table, $request),
            $paginate ? $table->getPage() : null,
            $paginate ? $table->getRowsPerPage() : null
        );

        if ($paginate) {
            $table->setTotalRows($apiResult->getNbTotalRows());
            $table->setFilteredRows($apiResult->getNbFilteredRows());

            $table->setLastPage(ceil($table->getFilteredRows() / $table->getRowsPerPage()));

            if ($table->getPage() > $table->getLastPage()) {
                $table->setPage($table->getLastPage());
            }
        }

        return $apiResult->getRows();
    }

    /**
     * @inheritdoc
     */
    public function loadRowsById(TableInterface $table, $identifiers)
    {
        throw new \Exception('this method should be overridden');
    }
}
