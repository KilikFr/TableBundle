<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\ApiTable;
use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Tests\Fixtures\Api\FakeApi;
use Symfony\Component\HttpFoundation\Request;

/**
 * Sorts defined with \SortDirection (PHP 8.6, symfony/polyfill-php86) work with every supported Doctrine ORM.
 */
final class SortDirectionTest extends IntegrationTestCase
{
    private const NAME_ASC = [3, 4, 17, 13, 8, 5, 18, 15, 20, 2, 16, 9, 22, 23, 21, 24, 6, 1, 14, 11, 19, 10, 12, 7, 25];

    public function testSortDirection(): void
    {
        $this->assertSame(self::NAME_ASC, $this->sortedIds(['p.name' => \SortDirection::Ascending], [], '0'));
        $this->assertSame(array_reverse(self::NAME_ASC), $this->sortedIds(['p.name' => \SortDirection::Descending], [], '0'));
    }

    public function testSortDirectionIsAutoInverted(): void
    {
        $this->assertSame(array_reverse(self::NAME_ASC), $this->sortedIds(['p.name' => \SortDirection::Ascending], [], '1'));
    }

    public function testExplicitReverseSortDirection(): void
    {
        $this->assertSame(
            array_reverse(self::NAME_ASC),
            $this->sortedIds(['p.name' => \SortDirection::Ascending], ['p.name' => \SortDirection::Descending], '1')
        );
    }

    public function testSortDirectionMixedWithStrings(): void
    {
        $rows = $this->sortedIds(['p.status' => 'ASC', 'p.price' => \SortDirection::Descending], [], '0');

        // cancelled (by price desc), then new, then validated
        $this->assertSame([19, 6, 12, 4], \array_slice($rows, 0, 4));
        $this->assertSame([14, 20, 7, 11, 5, 22, 16, 24, 2], \array_slice($rows, -9));
    }

    public function testApiTablesReceiveTheSortDirectionUnchanged(): void
    {
        $api = new FakeApi([], 0, 0);
        $table = (new ApiTable())
            ->setId('api')
            ->setPath('/api')
            ->setApi($api)
            ->addColumn((new Column())->setName('name')->setSort(['name' => \SortDirection::Descending]));

        $this->tableApiService()->getRows($table, new Request([], ['api_form' => ['sortColumn' => 'name']]));
        $this->tableApiService()->getRows($table, new Request([], ['api_form' => ['sortColumn' => 'name', 'sortReverse' => '1']]));

        $this->assertSame(['name' => \SortDirection::Descending], $api->calls[0]['orderBy']);
        $this->assertSame(['name' => \SortDirection::Ascending], $api->calls[1]['orderBy']);
    }

    /**
     * @param array<string, string|\SortDirection> $sort
     * @param array<string, string|\SortDirection> $sortReverse
     *
     * @return list<int>
     */
    private function sortedIds(array $sort, array $sortReverse, string $reverse): array
    {
        $table = $this->productTable((new Column())->setName('sorted')->setSort($sort)->setSortReverse($sortReverse));

        return self::productIds($this->tableService()->getRows(
            $table,
            $this->postRequest($table, ['sortColumn' => 'sorted', 'sortReverse' => $reverse], ['rowsPerPage' => '100'])
        ));
    }
}
