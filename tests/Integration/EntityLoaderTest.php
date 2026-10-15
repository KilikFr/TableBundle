<?php

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

    public function testCallbackModeWithUuidIdentifiers(): void
    {
        $received = [];
        $table = $this->documentTable()->setEntityLoaderCallback(static function (array $identifiers) use (&$received) {
            $received = $identifiers;

            return static::$em->getRepository(Document::class)->findBy(['id' => $identifiers]);
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
