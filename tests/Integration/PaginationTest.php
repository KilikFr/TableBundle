<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\SqlOutputWalker;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\QueryBuilder;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Services\TableService;
use Kilik\TableBundle\Tests\Fixtures\Entity\Category;
use Kilik\TableBundle\Tests\Fixtures\Fixtures;

/**
 * Freezes pagination and row counting in TableService::getRows().
 */
final class PaginationTest extends IntegrationTestCase
{
    public function testDefaultsWithoutParameters(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame(range(1, 10), self::productIds($rows));
        $this->assertSame(10, $table->getRowsPerPage());
        $this->assertSame(1, $table->getPage());
        $this->assertSame(25, $table->getTotalRows());
        $this->assertSame(25, $table->getFilteredRows());
        $this->assertSame(3, $table->getLastPage());
        $this->assertSame(1, $table->getFirstRow());
        $this->assertSame(10, $table->getLastRow());
        $this->assertSame(2, $table->getNextPage());
        $this->assertSame(0, $table->getPreviousPage());
    }

    public function testSecondPage(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['page' => '2']));

        $this->assertSame(range(11, 20), self::productIds($rows));
        $this->assertSame(2, $table->getPage());
        $this->assertSame(11, $table->getFirstRow());
        $this->assertSame(20, $table->getLastRow());
    }

    public function testLastPageIsPartial(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['page' => '3']));

        $this->assertSame(range(21, 25), self::productIds($rows));
        $this->assertSame(3, $table->getPage());
        $this->assertSame(25, $table->getLastRow());
        $this->assertSame(3, $table->getNextPage());
    }

    public function testPageBeyondLastPageIsMovedToLastPage(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['page' => '99']));

        $this->assertSame(range(21, 25), self::productIds($rows));
        $this->assertSame(3, $table->getPage());
    }

    /**
     * @dataProvider invalidPageProvider
     */
    public function testInvalidPageFallsBackToFirstPage(string $page): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['page' => $page]));

        $this->assertSame(range(1, 10), self::productIds($rows));
        $this->assertSame(1, $table->getPage());
    }

    public function invalidPageProvider(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'not a number' => ['abc'];
        yield 'empty' => [''];
    }

    public function testRowsPerPage(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '7', 'page' => '4']));

        $this->assertSame(range(22, 25), self::productIds($rows));
        $this->assertSame(7, $table->getRowsPerPage());
        $this->assertSame(4, $table->getLastPage());
        $this->assertSame(4, $table->getPage());
    }

    public function testRowsPerPageOutsideOfTheOptionsIsAccepted(): void
    {
        $table = $this->productTable()->setRowsPerPageOptions([5, 10]);
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '3']));

        $this->assertSame([1, 2, 3], self::productIds($rows));
        $this->assertSame(3, $table->getRowsPerPage());
        $this->assertSame(9, $table->getLastPage());
    }

    public function testHugeRowsPerPageReturnsTheWholeTable(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '100000']));

        $this->assertSame(range(1, 25), self::productIds($rows));
        $this->assertSame(1, $table->getLastPage());
    }

    /**
     * @group known-bug
     *
     * Current behavior: rowsPerPage=0 (or any non numeric value) is not validated.
     */
    public function testRowsPerPageZeroThrowsDivisionByZero(): void
    {
        $table = $this->productTable();

        $this->expectException(\DivisionByZeroError::class);
        $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '0']));
    }

    public function testFilteredRowsAndTotalRows(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'));
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => 'apple']));

        $this->assertSame([1, 2, 3], self::productIds($rows));
        $this->assertSame(25, $table->getTotalRows());
        $this->assertSame(3, $table->getFilteredRows());
        $this->assertSame(1, $table->getLastPage());
        $this->assertSame(3, $table->getLastRow());
    }

    public function testFiltersWithoutValueKeepAllRows(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'), $this->filteredColumn('p.status'));
        $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => '  ', 'p_status' => '']));

        $this->assertSame(25, $table->getTotalRows());
        $this->assertSame(25, $table->getFilteredRows());
        $this->assertSame(3, $table->getLastPage());
    }

    public function testQueryPartBuilderCalledWithoutValueFiltersTheRows(): void
    {
        $filter = (new Filter())->setQueryPartBuilder(static function (Filter $filter, Table $table, QueryBuilder $queryBuilder): void {
            $queryBuilder->andWhere("p.status = 'cancelled'");
        });
        $table = $this->productTable($this->filteredColumn('p.status', $filter));
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, ['p_status' => '']));

        $this->assertSame([4, 6, 12, 19], self::productIds($rows));
        $this->assertSame(25, $table->getTotalRows());
        $this->assertSame(4, $table->getFilteredRows());
    }

    public function testOverriddenTotalRowsDoNotChangeTheFilteredRows(): void
    {
        $service = new class(static::getContainer()->get('twig'), static::getContainer()->get('form.factory')) extends TableService {
            protected function setTotalRows(Table $table)
            {
                $table->setTotalRows(1000);
            }
        };
        $table = $this->productTable();
        $service->getRows($table, $this->postRequest($table));

        $this->assertSame(1000, $table->getTotalRows());
        $this->assertSame(25, $table->getFilteredRows());
    }

    public function testOverriddenCountRowsCountsTheTotalAndFilteredRows(): void
    {
        $service = new class(static::getContainer()->get('twig'), static::getContainer()->get('form.factory')) extends TableService {
            protected function countRows(QueryBuilder $qb, ?string $identifiers = null)
            {
                return 42;
            }
        };
        $table = $this->productTable();
        $service->getRows($table, $this->postRequest($table));

        $this->assertSame(42, $table->getTotalRows());
        $this->assertSame(42, $table->getFilteredRows());
    }

    public function testNoResult(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'));
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => 'nothing matches'], ['page' => '2']));

        $this->assertSame([], $rows);
        $this->assertSame(25, $table->getTotalRows());
        $this->assertSame(0, $table->getFilteredRows());
        $this->assertSame(0, $table->getLastPage());
        $this->assertSame(1, $table->getPage());
        $this->assertSame(1, $table->getFirstRow());
        $this->assertSame(0, $table->getLastRow());
    }

    public function testWithoutPaginationAllRowsAreReturnedAndNothingIsCounted(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['page' => '2', 'rowsPerPage' => '5']), false);

        $this->assertSame(range(1, 25), self::productIds($rows));
        $this->assertSame(5, $table->getRowsPerPage());
        $this->assertSame(2, $table->getPage());
        foreach (static::$queryLogger->getQueries() as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('count(', $sql);
        }
    }

    /**
     * Default strategy (no identifier field names): Doctrine's Paginator counts distinct root entities.
     */
    public function testToManyJoinIsCountedOnDistinctRootEntities(): void
    {
        $table = $this->categoryJoinTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '100']));

        // one row per (category, product) pair: 8 + 7 + 7 products + the empty category
        $this->assertCount(23, $rows);
        $this->assertSame(4, $table->getTotalRows());
        $this->assertSame(4, $table->getFilteredRows());
        $this->assertSame(1, $table->getLastPage());
    }

    /**
     * Identifier strategy: COUNT(identifier) counts joined rows, not distinct entities.
     */
    public function testIdentifierCountStrategyCountsJoinedRows(): void
    {
        $table = $this->categoryJoinTable()->setDefaultIdentifierFieldNames();
        $this->assertSame('c.id', $table->getIdentifierFieldNames());

        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '100']));

        $this->assertCount(23, $rows);
        $this->assertSame(23, $table->getTotalRows());
        $this->assertSame(23, $table->getFilteredRows());
    }

    public function testIdentifierCountStrategyWithoutJoin(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'))->setDefaultIdentifierFieldNames();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => 'apple']));

        $this->assertSame([1, 2, 3], self::productIds($rows));
        $this->assertSame(25, $table->getTotalRows());
        $this->assertSame(3, $table->getFilteredRows());
    }

    /**
     * The identifier strategy keeps the ORDER BY of the initial query builder in the COUNT query.
     * Doctrine's Paginator (default strategy) removes it.
     */
    public function testIdentifierCountStrategyWithAnInitialOrderBy(): void
    {
        $table = $this->productTable()->setDefaultIdentifierFieldNames();
        $table->getQueryBuilder()->orderBy('p.name');

        if ('postgresql' === self::platform()) {
            // COUNT() mixed with a non aggregated ORDER BY column is rejected by PostgreSQL
            $this->expectException(\Doctrine\DBAL\Exception::class);
        }

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame(25, $table->getTotalRows());
        $this->assertSame([3, 4, 17, 13, 8, 5, 18, 15, 20, 2], self::productIds($rows));
    }

    /**
     * @group known-bug
     *
     * Documented limitation: the identifier strategy cannot count grouped queries.
     */
    public function testIdentifierCountStrategyWithGroupByThrows(): void
    {
        $table = $this->categoryTable()->setDefaultIdentifierFieldNames();

        $this->expectException(NonUniqueResultException::class);
        $this->tableService()->getRows($table, $this->postRequest($table));
    }

    /**
     * With a custom output walker as default query hint and no identifier, a native COUNT(*) sub-query is used.
     */
    public function testNativeCountWithCustomOutputWalker(): void
    {
        $configuration = static::$em->getConfiguration();
        // SqlOutputWalker (ORM >= 3.3) replaces SqlWalker as output walker
        $walker = class_exists(SqlOutputWalker::class) ? SqlOutputWalker::class : SqlWalker::class;
        $configuration->setDefaultQueryHint(Query::HINT_CUSTOM_OUTPUT_WALKER, $walker);

        try {
            $table = $this->productTable($this->filteredColumn('p.name'));
            $rows = $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => 'apple']));
        } finally {
            $configuration->setDefaultQueryHints([]);
        }

        $this->assertSame([1, 2, 3], self::productIds($rows));
        $this->assertSame(25, $table->getTotalRows());
        $this->assertSame(3, $table->getFilteredRows());
        $countQueries = array_values(array_filter(
            static::$queryLogger->getQueries(),
            static fn (string $sql) => str_starts_with($sql, 'SELECT COUNT(*) AS dctrn_count FROM (SELECT ')
        ));
        $this->assertCount(2, $countQueries);
    }

    public function testGroupByQueryIsCountedOnGroups(): void
    {
        $table = $this->categoryTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame([1, 2, 3, 4], array_map('intval', self::column($rows, 'c_id')));
        $this->assertSame(4, $table->getTotalRows());
        $this->assertSame(4, $table->getFilteredRows());
    }

    /**
     * Categories left joined to their products, without group by: one row per product.
     */
    /**
     * @dataProvider countedQueryProvider
     *
     * @param array<string, string> $filters
     */
    public function testRowsAreCountedOnDistinctRootEntities(string $query, string $filteredField, array $filters, int $expectedTotal, int $expectedFiltered): void
    {
        $queryBuilder = match ($query) {
            'fetch joined to-one' => $this->productQueryBuilder()->addSelect('c')->leftJoin('p.category', 'c'),
            'fetch joined to-many' => static::$em->createQueryBuilder()->select('c', 'p')->from(Category::class, 'c')->leftJoin('c.products', 'p'),
            'joined to-many' => static::$em->createQueryBuilder()->select('c')->from(Category::class, 'c')->leftJoin('c.products', 'p'),
            'distinct' => $this->productQueryBuilder()->distinct(),
            'computed scalar' => $this->productQueryBuilder()->addSelect('p.price * 2 AS doublePrice'),
            'hidden scalar' => $this->productQueryBuilder()->addSelect('LENGTH(p.name) AS HIDDEN nameLength')->orderBy('nameLength'),
            'ordered' => $this->productQueryBuilder()->orderBy('p.name'),
            'ordered with a parameter' => $this->productQueryBuilder()
                ->orderBy('CASE WHEN p.status = :status THEN 0 ELSE 1 END')
                ->setParameter('status', 'new'),
            'grouped and ordered' => static::$em->createQueryBuilder()
                ->select('c', 'COUNT(p.id) AS nbProducts')
                ->from(Category::class, 'c')
                ->leftJoin('c.products', 'p')
                ->groupBy('c.id')
                ->orderBy('nbProducts'),
        };
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $table = (new Table())
            ->setId('counted')
            ->setPath('/counted')
            ->setQueryBuilder($queryBuilder, $rootAlias)
            ->addColumn($this->filteredColumn($filteredField));

        $this->tableService()->getRows($table, $this->postRequest($table, $filters));

        $this->assertSame($expectedTotal, $table->getTotalRows());
        $this->assertSame($expectedFiltered, $table->getFilteredRows());
    }

    public function countedQueryProvider(): iterable
    {
        $products = Fixtures::PRODUCTS;
        // categories of the products whose name contains the word
        $categoriesOf = static fn (string $word): int => \count(array_unique(array_filter(array_column(
            array_filter($products, static fn (array $product): bool => str_contains($product[0], $word)),
            6
        ))));
        $fruits = \count(array_filter($products, static fn (array $product): bool => 'fruits' === $product[6]));

        yield 'fetch joined to-one' => ['fetch joined to-one', 'c.name', ['c_name' => '==fruits'], 25, $fruits];
        yield 'fetch joined to-many' => ['fetch joined to-many', 'p.name', ['p_name' => 'apple'], 4, $categoriesOf('apple')];
        yield 'joined to-many' => ['joined to-many', 'p.name', ['p_name' => 'e'], 4, $categoriesOf('e')];
        yield 'distinct' => ['distinct', 'p.name', ['p_name' => 'apple'], 25, 3];
        yield 'computed scalar' => ['computed scalar', 'p.name', ['p_name' => 'apple'], 25, 3];
        yield 'hidden scalar ordering the rows' => ['hidden scalar', 'p.name', ['p_name' => 'apple'], 25, 3];
        yield 'ordered' => ['ordered', 'p.name', ['p_name' => 'apple'], 25, 3];
        yield 'ordered with a parameter' => ['ordered with a parameter', 'p.name', ['p_name' => 'apple'], 25, 3];
        yield 'grouped and ordered' => ['grouped and ordered', 'c.name', ['c_name' => 'o'], 4, 1];
    }

    /**
     * An aggregate without GROUP BY makes a single row, rejected by MySQL and PostgreSQL with the entity columns.
     */
    public function testAggregateWithoutGroupByIsCountedAsASingleRow(): void
    {
        $table = $this->productTable()->setQueryBuilder($this->productQueryBuilder()->addSelect('COUNT(p.id) AS nbProducts'), 'p');

        if (\in_array(self::platform(), ['mysql', 'postgresql'], true)) {
            $this->expectException(\Doctrine\DBAL\Exception::class);
        }
        $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame(1, $table->getTotalRows());
        $this->assertSame(1, $table->getFilteredRows());
    }

    private function categoryJoinTable(): Table
    {
        $queryBuilder = static::$em->createQueryBuilder()
            ->select('c')
            ->from(Category::class, 'c')
            ->leftJoin('c.products', 'p');

        return (new Table())
            ->setId('categories')
            ->setPath('/categories')
            ->setQueryBuilder($queryBuilder, 'c');
    }
}
