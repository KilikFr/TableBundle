<?php

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Tests\Fixtures\Entity\Product;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Freezes AbstractTableService::getSelectedRows() (mass actions).
 */
final class SelectedRowsTest extends IntegrationTestCase
{
    public function testRepositoryModeLoadsTheSelectedEntities(): void
    {
        $table = $this->productTable()->setEntityLoaderRepository(Product::class);
        $request = new Request([], ['kilik_products_selected' => ['3', '5']]);

        $entities = $this->tableService()->getSelectedRows($request, $table);

        $ids = array_map(static fn (Product $product) => $product->getId(), $entities);
        sort($ids);
        $this->assertSame([3, 5], $ids);
    }

    public function testCallbackModeReceivesThePostedIdentifiers(): void
    {
        $received = null;
        $table = $this->productTable()->setEntityLoaderCallback(static function (array $identifiers) use (&$received) {
            $received = $identifiers;

            return ['loaded'];
        });

        $result = $this->tableService()->getSelectedRows(new Request([], ['kilik_products_selected' => ['3', '5']]), $table);

        $this->assertSame(['3', '5'], $received);
        $this->assertSame(['loaded'], $result);
    }

    public function testSelectionInTheQueryStringIsIgnored(): void
    {
        $received = null;
        $table = $this->productTable()->setEntityLoaderCallback(static function (array $identifiers) use (&$received) {
            $received = $identifiers;

            return [];
        });

        $this->tableService()->getSelectedRows(new Request(['kilik_products_selected' => ['3']]), $table);

        $this->assertSame([], $received);
    }

    public function testEmptySelectionInRepositoryMode(): void
    {
        $table = $this->productTable()->setEntityLoaderRepository(Product::class);

        $this->assertSame([], $this->tableService()->getSelectedRows(new Request(), $table));
    }

    public function testSelectionMustBeAnArray(): void
    {
        $table = $this->productTable()->setEntityLoaderRepository(Product::class);

        $this->expectException(BadRequestException::class);
        $this->tableService()->getSelectedRows(new Request([], ['kilik_products_selected' => '3']), $table);
    }

    public function testLegacyModeIsNotSupported(): void
    {
        $table = $this->productTable();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported entity loader mode');
        $this->tableService()->getSelectedRows(new Request([], ['kilik_products_selected' => ['3']]), $table);
    }
}
