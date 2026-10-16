<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Tests\Fixtures\Entity\Category;
use Kilik\TableBundle\Tests\Fixtures\Fixtures;

/**
 * Freezes the computation of total columns (Column::useTotal()).
 */
final class TotalColumnsTest extends IntegrationTestCase
{
    public function testTotalsAreComputedOnAllFilteredRows(): void
    {
        $table = $this->productTableWithTotals();
        $this->expectTotalQueryFailureOnStrictPlatforms();

        $rows = $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => 'apple'], ['rowsPerPage' => '2', 'page' => '2']));

        $this->assertSame([3], self::productIds($rows));
        $this->assertTrue($table->haveTotalColumns());
        $this->assertEquals(650, $table->getColumnByName('p_price')->getTotal());
        $this->assertEquals(27, $table->getColumnByName('p_quantity')->getTotal());
        $this->assertNull($table->getColumnByName('p_name')->getTotal());
    }

    public function testTotalsWithoutFilter(): void
    {
        $table = $this->productTableWithTotals();
        $this->expectTotalQueryFailureOnStrictPlatforms();

        $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertEquals(array_sum(array_column(Fixtures::PRODUCTS, 3)), $table->getColumnByName('p_price')->getTotal());
        $this->assertEquals(array_sum(array_column(Fixtures::PRODUCTS, 4)), $table->getColumnByName('p_quantity')->getTotal());
    }

    public function testDisplayCallbackIsAppliedToTheTotal(): void
    {
        $table = $this->productTableWithTotals();
        $table->getColumnByName('p_price')->setDisplayCallback(static fn ($value) => 'EUR '.$value);
        $this->expectTotalQueryFailureOnStrictPlatforms();

        $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => 'apple']));

        $this->assertSame('EUR 650', $table->getColumnByName('p_price')->getTotal());
    }

    public function testTotalWithoutResultIsZero(): void
    {
        $table = $this->productTableWithTotals();
        $this->expectTotalQueryFailureOnStrictPlatforms();

        $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => 'nothing matches']));

        $this->assertSame(0, $table->getColumnByName('p_price')->getTotal());
    }

    /**
     * On a grouped query, the total query keeps the GROUP BY: the totals are those of the first group only.
     */
    public function testTotalsOnAGroupedQueryAreThoseOfTheFirstGroup(): void
    {
        $table = $this->categoryTableWithTotals();

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertCount(4, $rows);
        // fruits: 120 + 80 + 450 + 60 + 300 + 280 + 70 + 90
        $this->assertEquals(1450, $table->getColumnByName('sumPrice')->getTotal());
    }

    public function testTotalsOnAGroupedQueryDependOnTheOrder(): void
    {
        $table = $this->categoryTableWithTotals();

        $this->tableService()->getRows($table, $this->postRequest($table, ['sortColumn' => 'c_name', 'sortReverse' => '1']));

        // tools: 1500 + 800 + 3500 + 1200 + 2200 + 8900 + 400
        $this->assertEquals(18500, $table->getColumnByName('sumPrice')->getTotal());
    }

    public function testTotalsOnAQueryGroupedByTheRootIdentifierAreThoseOfTheFirstGroupInTheOrder(): void
    {
        $table = $this->categoryTableWithTotals();
        $table->getColumnByName('sumPrice')->setSort(['sumPrice' => 'desc']);
        // without the empty category, whose NULL sum is sorted first or last depending on the platform
        $table->getQueryBuilder()->andWhere('p.id IS NOT NULL');

        $this->tableService()->getRows($table, $this->postRequest($table, ['sortColumn' => 'sumPrice']));

        $this->assertEquals(max(self::pricesByCategory()), $table->getColumnByName('sumPrice')->getTotal());
    }

    public function testTotalsOnAQueryGroupedByTheRootIdentifierAndFields(): void
    {
        $table = $this->categoryTableWithTotals();
        $table->getQueryBuilder()->addGroupBy('c.name');

        $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertEquals(self::pricesByCategory()['fruits'], $table->getColumnByName('sumPrice')->getTotal());
    }

    /**
     * With several rows per root entity, the totals are those of the last row of the first root entity.
     */
    public function testTotalsOnAQueryWithSeveralRowsPerRootEntity(): void
    {
        $table = $this->categoryTableWithTotals();
        // without the empty category, whose NULL status is sorted first or last depending on the platform
        $table->getQueryBuilder()->addGroupBy('p.status')->orderBy('p.status')->andWhere('p.id IS NOT NULL');

        $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '20']));

        // rows: (fruits, cancelled), (games, cancelled), ..., (fruits, new), ..., (fruits, validated), ...
        $this->assertEquals(self::pricesByCategory('validated')['fruits'], $table->getColumnByName('sumPrice')->getTotal());
    }

    /**
     * With INDEX BY, the rows of the total query are indexed by identifier: there is no row 0.
     */
    public function testTotalsOnAQueryIndexedByIdentifierAreZero(): void
    {
        $table = $this->categoryTableWithTotals();
        $queryBuilder = $table->getQueryBuilder();
        $queryBuilder->resetDQLPart('from')->from(Category::class, 'c', 'c.id');

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertCount(4, $rows);
        $this->assertSame(0, $table->getColumnByName('sumPrice')->getTotal());
    }

    /**
     * @group known-bug
     *
     * Current behavior: a total column requires a filter, used to get the summed field.    /**
     * @group known-bug
     *
     * Current behavior: a total column requires a filter, used to get the summed field.
     */
    public function testTotalColumnWithoutFilterThrows(): void
    {
        $table = $this->productTable((new Column())->setName('p_price')->useTotal());

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('getField() on null');
        $this->tableService()->getRows($table, $this->postRequest($table));
    }

    public function testTotalsAreNotComputedWithoutTotalColumn(): void
    {
        $table = $this->productTable($this->filteredColumn('p.price'));

        $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertFalse($table->haveTotalColumns());
        $this->assertNull($table->getColumnByName('p_price')->getTotal());
    }

    private function productTableWithTotals(): Table
    {
        return $this->productTable(
            $this->filteredColumn('p.name'),
            $this->filteredColumn('p.price')->useTotal(),
            $this->filteredColumn('p.quantity')->useTotal(),
        );
    }

    private function categoryTableWithTotals(): Table
    {
        return $this->categoryTable(
            $this->filteredColumn('c.name')->setSort(['c.name' => 'asc']),
            (new Column())
                ->setName('sumPrice')
                ->setFilter((new Filter())->setName('sumPrice')->setField('p.price')->setHaving(true))
                ->useTotal(),
        );
    }

    /**
     * Sum of the product prices by category name.
     *
     * @return array<string, int>
     */
    private static function pricesByCategory(?string $status = null): array
    {
        $prices = [];
        foreach (Fixtures::PRODUCTS as $product) {
            if (null !== $product[6] && (null === $status || $status === $product[2])) {
                $prices[$product[6]] = ($prices[$product[6]] ?? 0) + $product[3];
            }
        }

        return $prices;
    }

    /**
     * Without GROUP BY, the total query mixes SUM() with the selected columns: MySQL (ONLY_FULL_GROUP_BY) and
     * PostgreSQL reject it, SQLite and MariaDB (no ONLY_FULL_GROUP_BY by default) accept it.
     */
    private function expectTotalQueryFailureOnStrictPlatforms(): void
    {
        if (\in_array(self::platform(), ['mysql', 'postgresql'], true)) {
            $this->expectException(\Doctrine\DBAL\Exception::class);
        }
    }
}
