<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Tests\Fixtures\Entity\Category;
use Kilik\TableBundle\Tests\Fixtures\Entity\Document;
use Kilik\TableBundle\Tests\Fixtures\Entity\Product;
use Kilik\TableBundle\Tests\Fixtures\Fixtures;

/**
 * Freezes how entities are attached to the rows ($row['object']) for each entity loader mode.
 */
final class EntityLoaderTest extends IntegrationTestCase
{
    public function testDefaultLoaderIsLegacy(): void
    {
        $this->assertSame(Table::ENTITY_LOADER_LEGACY, (new Table())->getEntityLoaderMode());
    }

    public function testLoaderLegacyAttachesEntities(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertCount(10, $rows);
        foreach ($rows as $row) {
            $this->assertInstanceOf(Product::class, $row['object']);
            $this->assertSame($row['p_id'], $row['object']->getId());
            $this->assertSame($row['p_name'], $row['object']->getName());
        }
    }

    public function testLoaderLegacyWithoutObjects(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table), true, false);

        $this->assertCount(10, $rows);
        $this->assertArrayNotHasKey('object', $rows[0]);
        $this->assertSame(['p_id', 'p_name', 'p_description', 'p_status', 'p_price', 'p_quantity', 'p_createdAt'], array_keys($rows[0]));
    }

    public function testLoaderLegacyWithMixedResults(): void
    {
        $table = $this->categoryTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame(['c_id', 'c_name', 'nbProducts', 'sumPrice', 'object'], array_keys($rows[0]));
        foreach ($rows as $row) {
            $this->assertInstanceOf(Category::class, $row['object']);
            $this->assertSame($row['c_id'], $row['object']->getId());
        }
        $this->assertSame(Fixtures::CATEGORIES, array_map(static fn (array $row) => $row['object']->getName(), $rows));
    }

    public function testLoaderLegacyWithDuplicatedRows(): void
    {
        $queryBuilder = static::$em->createQueryBuilder()
            ->select('c')
            ->from(Category::class, 'c')
            ->leftJoin('c.products', 'p');
        $table = (new Table())->setId('categories')->setPath('/categories')->setQueryBuilder($queryBuilder, 'c');

        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '100']));

        $this->assertCount(23, $rows);
        foreach ($rows as $row) {
            $this->assertSame($row['c_id'], $row['object']->getId());
        }
    }

    public function testLoaderLegacyWithUuidIdentifiers(): void
    {
        $table = $this->documentTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame(array_values(Fixtures::DOCUMENTS), self::column($rows, 'd_title'));
        foreach ($rows as $row) {
            $this->assertInstanceOf(Document::class, $row['object']);
            $this->assertSame($row['d_title'], $row['object']->getTitle());
        }
    }

    public function testRepositoryMode(): void
    {
        $table = $this->productTable()->setEntityLoaderRepository(Product::class);
        $this->assertSame(Table::ENTITY_LOADER_REPOSITORY, $table->getEntityLoaderMode());

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertCount(10, $rows);
        foreach ($rows as $row) {
            $this->assertInstanceOf(Product::class, $row['object']);
            $this->assertSame($row['p_id'], $row['object']->getId());
        }
        $queries = static::$queryLogger->getQueries();
        $this->assertStringContainsString(' IN (', end($queries));
    }

    /**
     * Identifiers are bound as strings: binary UUID columns (SQLite, MySQL) match nothing, then no "object" key is set.
     */
    public function testRepositoryModeWithUuidIdentifiers(): void
    {
        $table = $this->documentTable()->setEntityLoaderRepository(Document::class);
        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertCount(3, $rows);
        if ('postgresql' !== self::platform()) {
            $this->assertArrayNotHasKey('object', $rows[0]);

            return;
        }
        foreach ($rows as $row) {
            $this->assertInstanceOf(Document::class, $row['object']);
            $this->assertSame($row['d_title'], $row['object']->getTitle());
        }
    }

    /**
     * @group known-bug
     *
     * Current behavior: the intended InvalidArgumentException is unreachable because of the getter return type.
     */
    public function testRepositoryModeWithoutRepositoryThrowsATypeError(): void
    {
        $table = $this->productTable()->setEntityLoaderMode(Table::ENTITY_LOADER_REPOSITORY);

        $this->expectException(\TypeError::class);
        $this->tableService()->getRows($table, $this->postRequest($table));
    }

    public function testCallbackMode(): void
    {
        $received = [];
        $table = $this->productTable()->setEntityLoaderCallback(static function (array $identifiers) use (&$received) {
            $received[] = $identifiers;

            return array_reverse(static::$em->getRepository(Product::class)->findBy(['id' => $identifiers]));
        });
        $this->assertSame(Table::ENTITY_LOADER_CALLBACK, $table->getEntityLoaderMode());

        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['page' => '2']));

        $this->assertSame([range(11, 20)], $received);
        foreach ($rows as $row) {
            $this->assertSame($row['p_id'], $row['object']->getId());
        }
    }

    public function testCallbackModeWithMissingEntities(): void
    {
        $table = $this->productTable()->setEntityLoaderCallback(
            static fn (array $identifiers) => static::$em->getRepository(Product::class)->findBy(['id' => [2, 3]])
        );

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame(
            [null, 2, 3, null, null, null, null, null, null, null],
            array_map(static fn (array $row) => $row['object'] ? $row['object']->getId() : null, $rows)
        );
    }

    public function testCallbackModeWithoutEntities(): void
    {
        $table = $this->productTable()->setEntityLoaderCallback(static fn (array $identifiers) => []);

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertCount(10, $rows);
        $this->assertArrayNotHasKey('object', $rows[0]);
    }

    /**
     * Rows and entities are matched with a loose comparison (==).
     */
    public function testCallbackModeMatchesIdentifiersLoosely(): void
    {
        $entity = new class {
            public function getId(): string
            {
                return '3';
            }
        };
        $table = $this->productTable()->setEntityLoaderCallback(static fn (array $identifiers) => [$entity]);

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame($entity, $rows[2]['object']);
        $this->assertNull($rows[0]['object']);
    }

    /**
     * Each row gets the first entity whose identifier is loosely equal (==) to the row identifier.
     *
     * @dataProvider looseIdentifierProvider
     *
     * @param list<mixed>          $entityIdentifiers
     * @param array<int, int|null> $expectedEntityIndexes entity index by row identifier
     */
    public function testCallbackModeAttachesTheFirstLooselyEqualEntity(array $entityIdentifiers, array $expectedEntityIndexes): void
    {
        $entities = array_map(static fn ($identifier) => new class($identifier) {
            public function __construct(private $identifier)
            {
            }

            public function getId()
            {
                return $this->identifier;
            }
        }, $entityIdentifiers);
        $table = $this->productTable()->setEntityLoaderCallback(static fn (array $identifiers) => $entities);

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $attached = [];
        foreach ($rows as $row) {
            $index = array_search($row['object'], $entities, true);
            $attached[$row['p_id']] = false === $index ? null : $index;
        }
        $expected = [];
        foreach (range(1, 10) as $identifier) {
            $expected[$identifier] = $expectedEntityIndexes[$identifier] ?? null;
        }
        $this->assertSame($expected, $attached);
    }

    public function looseIdentifierProvider(): iterable
    {
        yield 'integers in another order, with a duplicate' => [[3, 1, 3, 2], [1 => 1, 2 => 3, 3 => 0]];
        yield 'numeric strings' => [['1.0', ' 2', '3 ', '4e0', '0x5', '06'], [1 => 0, 2 => 1, 3 => 2, 4 => 3, 6 => 5]];
        yield 'first of several equal strings' => [['01', '1', 1], [1 => 0]];
        yield 'non-numeric strings' => [['1a', 'a1', ''], []];
        yield 'floats' => [[1.0, 2.5, 3.0], [1 => 0, 3 => 2]];
        yield 'null' => [[null], []];
        yield 'true matches every row' => [[2, true], [1 => 1, 2 => 0, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1]];
        yield 'integers after a boolean' => [[false, 4], [4 => 1]];
    }

    public function testCallbackModeWithUuidIdentifiers(): void
    {
        $received = [];
        $table = $this->documentTable()->setEntityLoaderCallback(static function (array $identifiers) use (&$received) {
            $received = $identifiers;

            // filtered in PHP: older ORM 2.x versions do not convert Uuid objects in findBy()
            return array_values(array_filter(
                static::$em->getRepository(Document::class)->findAll(),
                static fn (Document $document) => \in_array((string) $document->getId(), array_map('strval', $identifiers), true)
            ));
        });

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame(array_keys(Fixtures::DOCUMENTS), array_map('strval', $received));
        foreach ($rows as $row) {
            $this->assertSame($row['d_title'], $row['object']->getTitle());
        }
    }

    public function testCallbackModeWithoutCallableThrows(): void
    {
        $table = $this->productTable()->setEntityLoaderMode(Table::ENTITY_LOADER_CALLBACK);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('entity loader callback is missing or not callable for ENTITY_LOADER_CALLBACK mode');
        $this->tableService()->getRows($table, $this->postRequest($table));
    }

    public function testNoneMode(): void
    {
        $table = $this->productTable()->setEntityLoaderMode(Table::ENTITY_LOADER_NONE);

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertCount(10, $rows);
        $this->assertArrayNotHasKey('object', $rows[0]);
    }

    public function testLoadRowsByIdInLegacyModeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported entity loader mode');
        $this->tableService()->loadRowsById($this->productTable(), [1, 2]);
    }

    /**
     * @dataProvider legacyTableProvider
     */
    public function testLoaderLegacyRowsHaveTheValuesOfTheRowsWithoutObjects(string $table): void
    {
        $createTable = fn (): Table => match ($table) {
            'products' => $this->productTable(),
            'categories' => $this->categoryTable(),
            'documents' => $this->documentTable(),
            'products with categories' => $this->productTable()->setQueryBuilder(
                $this->productQueryBuilder()->addSelect('c')->leftJoin('p.category', 'c'),
                'p'
            ),
            'products with a scalar named like a foreign key' => $this->productTable()->setQueryBuilder(
                $this->productQueryBuilder()->addSelect('p.price AS p_category_id'),
                'p'
            ),
        };
        $withObjectsTable = $createTable();
        $withoutObjectsTable = $createTable();

        $withObjects = $this->tableService()->getRows($withObjectsTable, $this->postRequest($withObjectsTable, [], ['rowsPerPage' => '100']));
        $withoutObjects = $this->tableService()->getRows($withoutObjectsTable, $this->postRequest($withoutObjectsTable, [], ['rowsPerPage' => '100']), true, false);

        $this->assertNotEmpty($withObjects);
        $this->assertSame(self::exportValues($withoutObjects), self::exportValues(array_map(static function (array $row): array {
            unset($row['object']);

            return $row;
        }, $withObjects)));
    }

    public function legacyTableProvider(): iterable
    {
        yield 'entities' => ['products'];
        yield 'entities and scalars' => ['categories'];
        yield 'uuid identifiers' => ['documents'];
        yield 'fetch join' => ['products with categories'];
        yield 'scalar named like a foreign key' => ['products with a scalar named like a foreign key'];
    }

    public function testLoaderLegacyAttachesTheManagedEntities(): void
    {
        $table = $this->productTable();
        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        foreach ($rows as $row) {
            $this->assertSame(static::$em->find(Product::class, $row['p_id']), $row['object']);
        }
    }

    public function testLoaderLegacyWithQueryParameters(): void
    {
        $queryBuilder = $this->productQueryBuilder()
            ->andWhere('p.category = :category')
            ->andWhere('p.createdAt >= :date')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('category', static::$em->getRepository(Category::class)->findOneBy(['name' => 'fruits']))
            ->setParameter('date', new \DateTimeImmutable('2024-02-01'))
            ->setParameter('statuses', ['new', 'validated']);
        $table = $this->productTable()->setQueryBuilder($queryBuilder, 'p');

        $rows = $this->tableService()->getRows($table, $this->postRequest($table, [], ['rowsPerPage' => '100']));

        $expectedIds = self::fixtureIds(static fn (array $product): bool => 'fruits' === $product[6]
            && $product[5] >= '2024-02-01'
            && \in_array($product[2], ['new', 'validated'], true));
        $this->assertNotEmpty($expectedIds);
        $this->assertSame($expectedIds, self::productIds($rows));
        foreach ($rows as $row) {
            $this->assertSame($row['p_id'], $row['object']->getId());
        }
    }

    public function testLoaderLegacyWithAParameterThatCannotBeSerialized(): void
    {
        $name = new class {
            private \Closure $value;

            public function __construct()
            {
                $this->value = static fn (): string => 'red apple';
            }

            public function __toString(): string
            {
                return ($this->value)();
            }
        };
        $queryBuilder = $this->productQueryBuilder()->andWhere('p.name = :name')->setParameter('name', $name);
        $table = $this->productTable()->setQueryBuilder($queryBuilder, 'p');

        $rows = $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame([1], self::productIds($rows));
        $this->assertSame(1, $rows[0]['object']->getId());
    }

    /**
     * Rows with objects replaced by comparable values.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private static function exportValues(array $rows): array
    {
        return array_map(static fn (array $row): array => array_map(static function ($value) {
            if ($value instanceof \DateTimeInterface) {
                return [$value::class, $value->format(\DateTimeInterface::ATOM)];
            }

            return \is_object($value) ? [$value::class, (string) $value] : $value;
        }, $row), $rows);
    }

    private function documentTable(): Table
    {
        $queryBuilder = static::$em->createQueryBuilder()->select('d')->from(Document::class, 'd');

        return (new Table())
            ->setId('documents')
            ->setPath('/documents')
            ->setQueryBuilder($queryBuilder, 'd')
            ->addColumn((new Column())->setName('d_title'));
    }
}
