<?php

namespace Kilik\TableBundle\Tests\Fixtures\Api;

use Kilik\TableBundle\Api\ApiInterface;
use Kilik\TableBundle\Api\StandardResult;
use Kilik\TableBundle\Components\TableInterface;

/**
 * API returning fixed rows and recording the arguments it receives.
 */
final class FakeApi implements ApiInterface
{
    /**
     * @var list<array{filters: mixed, orderBy: mixed, page: mixed, limit: mixed}>
     */
    public array $calls = [];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        private array $rows,
        private int $totalRows,
        private int $filteredRows,
    ) {
    }

    public function load(TableInterface $table, $filters, $orderBy = [], $page = null, $limit = null)
    {
        $this->calls[] = ['filters' => $filters, 'orderBy' => $orderBy, 'page' => $page, 'limit' => $limit];

        $result = (new StandardResult())
            ->setNbTotalRows($this->totalRows)
            ->setNbFilteredRows($this->filteredRows);
        foreach ($this->rows as $row) {
            $result->addRow($row);
        }

        return $result;
    }
}
