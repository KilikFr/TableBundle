<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Services\TableApiService;
use Kilik\TableBundle\Services\TableService;
use Kilik\TableBundle\Tests\Doctrine\EntityManagerFactory;
use Kilik\TableBundle\Tests\Doctrine\QueryLogger;
use Kilik\TableBundle\Tests\Fixtures\Entity\Category;
use Kilik\TableBundle\Tests\Fixtures\Entity\Product;
use Kilik\TableBundle\Tests\Fixtures\Fixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for tests running the services against a real ORM and database.
 *
 * The schema and the fixtures are created once per test class; tests must not write to the database.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected static ?EntityManager $em = null;

    protected static QueryLogger $queryLogger;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$queryLogger = new QueryLogger();
        static::$em = EntityManagerFactory::create(static::$queryLogger);
        EntityManagerFactory::createSchema(static::$em);
        Fixtures::load(static::$em);
    }

    public static function tearDownAfterClass(): void
    {
        if (null !== static::$em) {
            static::$em->getConnection()->close();
            static::$em = null;
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        static::$em->clear();
        static::$queryLogger->reset();
    }

    protected function tableService(): TableService
    {
        return static::getContainer()->get('kilik_table');
    }

    protected function tableApiService(): TableApiService
    {
        return static::getContainer()->get('kilik_table_api');
    }

    /**
     * Database platform the tests are running on: sqlite, mysql or postgresql.
     */
    protected static function platform(): string
    {
        $driver = static::$em->getConnection()->getParams()['driver'] ?? '';

        return match ($driver) {
            'pdo_mysql', 'mysqli' => 'mysql',
            'pdo_pgsql', 'pgsql' => 'postgresql',
            default => 'sqlite',
        };
    }

    protected function productQueryBuilder(): QueryBuilder
    {
        return static::$em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p');
    }

    /**
     * Table on products, with an id column and the given columns.
     */
    protected function productTable(Column ...$columns): Table
    {
        $table = (new Table())
            ->setId('products')
            ->setPath('/products')
            ->setQueryBuilder($this->productQueryBuilder(), 'p')
            ->addColumn((new Column())->setLabel('Id')->setName('p_id'));

        foreach ($columns as $column) {
            $table->addColumn($column);
        }

        return $table;
    }

    /**
     * Column with a filter, named like the scalar result key (alias_field).
     */
    protected function filteredColumn(string $field, ?Filter $filter = null): Column
    {
        $name = str_replace('.', '_', $field);
        $filter = ($filter ?? new Filter())->setField($field)->setName($name);

        return (new Column())->setLabel($name)->setFilter($filter);
    }

    /**
     * Table on categories with a to-many join and a group by.
     */
    protected function categoryTable(Column ...$columns): Table
    {
        $queryBuilder = static::$em->createQueryBuilder()
            ->select('c', 'COUNT(p.id) AS nbProducts', 'SUM(p.price) AS sumPrice')
            ->from(Category::class, 'c')
            ->leftJoin('c.products', 'p')
            ->groupBy('c.id');

        $table = (new Table())
            ->setId('categories')
            ->setPath('/categories')
            ->setQueryBuilder($queryBuilder, 'c')
            ->addColumn((new Column())->setLabel('Id')->setName('c_id'));

        foreach ($columns as $column) {
            $table->addColumn($column);
        }

        return $table;
    }

    /**
     * Request carrying the table form values in the POST body, as KilikTable.js does.
     *
     * @param array<string, mixed> $filters form values (filters, sortColumn, sortReverse)
     * @param array<string, mixed> $extra   other POST parameters (page, rowsPerPage, hiddenColumns)
     */
    protected function postRequest(Table $table, array $filters = [], array $extra = []): Request
    {
        return new Request([], array_merge([$table->getFormId() => $filters], $extra));
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<int|string>
     */
    protected static function column(array $rows, string $key): array
    {
        return array_values(array_map(static fn (array $row) => $row[$key], $rows));
    }

    /**
     * Product identifiers of the given rows.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<int>
     */
    protected static function productIds(array $rows): array
    {
        return array_map('intval', static::column($rows, 'p_id'));
    }

    /**
     * Product identifiers sorted ascending, for assertions that do not depend on the order.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<int>
     */
    protected static function sortedProductIds(array $rows): array
    {
        $ids = static::productIds($rows);
        sort($ids);

        return $ids;
    }

    /**
     * Identifiers of the fixture products matching the given predicate.
     *
     * @param callable(array{0: string, 1: ?string, 2: string, 3: int, 4: int, 5: ?string, 6: ?string}): bool $predicate
     *
     * @return list<int>
     */
    protected static function fixtureIds(callable $predicate): array
    {
        return array_keys(array_filter(Fixtures::PRODUCTS, $predicate));
    }
}
