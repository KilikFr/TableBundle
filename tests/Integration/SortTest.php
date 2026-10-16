<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Tests\Fixtures\Fixtures;

/**
 * Freezes the ordering applied by TableService::getRows().
 */
final class SortTest extends IntegrationTestCase
{
    public function testWithoutSortRowsAreOrderedById(): void
    {
        $this->assertOrder(range(1, 25), $this->sortableTable(), []);
    }

    public function testSortAscending(): void
    {
        $this->assertOrder(self::expectedIds(['name' => 'asc']), $this->sortableTable(), ['sortColumn' => 'p_name']);
    }

    /**
     * @dataProvider truthySortReverseProvider
     */
    public function testSortReverseInvertsTheSort(string $sortReverse): void
    {
        $this->assertOrder(
            self::expectedIds(['name' => 'desc']),
            $this->sortableTable(),
            ['sortColumn' => 'p_name', 'sortReverse' => $sortReverse]
        );
    }

    public function truthySortReverseProvider(): iterable
    {
        yield 'sent by KilikTable.js' => ['1'];
        yield 'the string "false" is truthy' => ['false'];
    }

    /**
     * @dataProvider falsySortReverseProvider
     */
    public function testFalsySortReverseKeepsTheSort(string $sortReverse): void
    {
        $this->assertOrder(
            self::expectedIds(['name' => 'asc']),
            $this->sortableTable(),
            ['sortColumn' => 'p_name', 'sortReverse' => $sortReverse]
        );
    }

    public function falsySortReverseProvider(): iterable
    {
        yield 'sent by KilikTable.js' => ['0'];
        yield 'empty' => [''];
    }

    public function testUppercaseDirectionIsAutoInverted(): void
    {
        $this->assertOrder(self::expectedIds(['price' => 'asc']), $this->sortableTable(), ['sortColumn' => 'p_price']);
        $this->assertOrder(self::expectedIds(['price' => 'desc']), $this->sortableTable(), ['sortColumn' => 'p_price', 'sortReverse' => '1']);
    }

    public function testSortOnSeveralFields(): void
    {
        $this->assertOrder(
            self::expectedIds(['status' => 'asc', 'name' => 'desc']),
            $this->sortableTable(),
            ['sortColumn' => 'p_status']
        );
        $this->assertOrder(
            self::expectedIds(['status' => 'desc', 'name' => 'asc']),
            $this->sortableTable(),
            ['sortColumn' => 'p_status', 'sortReverse' => '1']
        );
    }

    public function testExplicitReverseSort(): void
    {
        $this->assertOrder(self::expectedIds(['quantity' => 'asc']), $this->sortableTable(), ['sortColumn' => 'p_quantity']);
        $this->assertOrder(
            self::expectedIds(['quantity' => 'desc', 'name' => 'asc']),
            $this->sortableTable(),
            ['sortColumn' => 'p_quantity', 'sortReverse' => '1']
        );
    }

    /**
     * @dataProvider ignoredSortColumnProvider
     */
    public function testIgnoredSortColumn(string $sortColumn): void
    {
        $this->assertOrder(range(1, 25), $this->sortableTable(), ['sortColumn' => $sortColumn, 'sortReverse' => '1']);
    }

    public function ignoredSortColumnProvider(): iterable
    {
        yield 'unknown column' => ['unknown'];
        yield 'empty' => [''];
        yield 'column without sort' => ['p_description'];
        yield 'field instead of column name' => ['p.name'];
    }

    public function testInitialOrderByIsKeptWithoutSortColumn(): void
    {
        $table = $this->sortableTable();
        $table->getQueryBuilder()->orderBy('p.price');

        $this->assertOrder(self::expectedIds(['price' => 'asc']), $table, []);
    }

    public function testInitialOrderByIsReplacedBySortColumn(): void
    {
        $table = $this->sortableTable();
        $table->getQueryBuilder()->orderBy('p.price');

        $this->assertOrder(self::expectedIds(['name' => 'asc']), $table, ['sortColumn' => 'p_name']);
    }

    public function testInitialOrderByIsKeptForAColumnWithoutSort(): void
    {
        $table = $this->sortableTable();
        $table->getQueryBuilder()->orderBy('p.price');

        $this->assertOrder(self::expectedIds(['price' => 'asc']), $table, ['sortColumn' => 'p_description']);
    }

    public function testSortIsAppliedBeforePagination(): void
    {
        $table = $this->sortableTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, ['sortColumn' => 'p_price', 'sortReverse' => '1'], ['page' => '2', 'rowsPerPage' => '5']));

        $this->assertSame(\array_slice(self::expectedIds(['price' => 'desc']), 5, 5), self::productIds($rows));
    }

    private function sortableTable(): Table
    {
        return $this->productTable(
            $this->filteredColumn('p.name')->setSort(['p.name' => 'asc']),
            $this->filteredColumn('p.price')->setSort(['p.price' => 'ASC']),
            $this->filteredColumn('p.status')->setSort(['p.status' => 'asc', 'p.name' => 'DESC']),
            $this->filteredColumn('p.quantity')
                ->setSort(['p.quantity' => 'asc'])
                ->setSortReverse(['p.quantity' => 'desc', 'p.name' => 'asc']),
            (new Column())->setName('p_description'),
        );
    }

    /**
     * @param list<int>            $expectedIds
     * @param array<string, mixed> $form
     */
    private function assertOrder(array $expectedIds, Table $table, array $form): void
    {
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, $form, ['rowsPerPage' => '100']));

        $this->assertSame($expectedIds, self::productIds($rows));
    }

    /**
     * Fixture identifiers in the given order, ties broken by identifier.
     *
     * @param array<string, string> $orderBy field => asc|desc
     *
     * @return list<int>
     */
    private static function expectedIds(array $orderBy): array
    {
        $fieldIndexes = ['name' => 0, 'status' => 2, 'price' => 3, 'quantity' => 4];
        $ids = array_keys(Fixtures::PRODUCTS);
        usort($ids, static function (int $a, int $b) use ($orderBy, $fieldIndexes): int {
            foreach ($orderBy as $field => $direction) {
                $valueA = Fixtures::PRODUCTS[$a][$fieldIndexes[$field]];
                $valueB = Fixtures::PRODUCTS[$b][$fieldIndexes[$field]];
                $comparison = \is_int($valueA) ? $valueA <=> $valueB : strcmp($valueA, $valueB);
                if (0 !== $comparison) {
                    return 'desc' === $direction ? -$comparison : $comparison;
                }
            }

            return $a <=> $b;
        });

        return $ids;
    }
}
