<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\ApiTable;
use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Services\TableApiService;
use Kilik\TableBundle\Tests\Fixtures\Api\FakeApi;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Freezes TableApiService, used with tables loading their rows from an API.
 */
final class TableApiServiceTest extends KernelTestCase
{
    private const ROWS = [
        ['name' => 'alpha', 'status' => 'new'],
        ['name' => 'beta', 'status' => 'validated'],
    ];

    public function testDefaults(): void
    {
        [$table, $api] = $this->apiTable();

        $rows = $this->service()->getRows($table, new Request());

        $this->assertSame(self::ROWS, $rows);
        $this->assertSame([['filters' => [], 'orderBy' => [], 'page' => 1, 'limit' => 10]], $api->calls);
        $this->assertSame(50, $table->getTotalRows());
        $this->assertSame(20, $table->getFilteredRows());
        $this->assertSame(2, $table->getLastPage());
        $this->assertSame(1, $table->getPage());
    }

    public function testFiltersAreTrimmedAndEmptyValuesDropped(): void
    {
        [$table, $api] = $this->apiTable();

        $this->service()->getRows($table, new Request([], ['api_form' => ['name' => ' >al ', 'status' => '  ', 'unknown' => 'x']]));

        $this->assertSame(['name' => '>al'], $api->calls[0]['filters']);
    }

    public function testOrderBy(): void
    {
        [$table, $api] = $this->apiTable();
        $this->service()->getRows($table, new Request([], ['api_form' => ['sortColumn' => 'name']]));
        $this->assertSame(['name' => 'asc', 'status' => 'DESC'], $api->calls[0]['orderBy']);

        [$table, $api] = $this->apiTable();
        $this->service()->getRows($table, new Request([], ['api_form' => ['sortColumn' => 'name', 'sortReverse' => '1']]));
        $this->assertSame(['name' => 'desc', 'status' => 'asc'], $api->calls[0]['orderBy']);

        [$table, $api] = $this->apiTable();
        $this->service()->getRows($table, new Request([], ['api_form' => ['sortColumn' => 'status']]));
        $this->assertSame([], $api->calls[0]['orderBy']);
    }

    public function testPageBeyondTheLastPageIsRequestedThenMovedToTheLastPage(): void
    {
        [$table, $api] = $this->apiTable();

        $this->service()->getRows($table, new Request([], ['page' => '99', 'rowsPerPage' => '5']));

        $this->assertSame(99, $api->calls[0]['page']);
        $this->assertSame(5, $api->calls[0]['limit']);
        $this->assertSame(4, $table->getLastPage());
        $this->assertSame(4, $table->getPage());
    }

    public function testWithoutPagination(): void
    {
        [$table, $api] = $this->apiTable();

        $rows = $this->service()->getRows($table, new Request([], ['page' => '2']), false);

        $this->assertSame(self::ROWS, $rows);
        $this->assertNull($api->calls[0]['page']);
        $this->assertNull($api->calls[0]['limit']);
    }

    public function testHiddenColumns(): void
    {
        [$table] = $this->apiTable();

        $this->service()->getRows($table, new Request([], ['hiddenColumns' => ['status' => '1']]));

        $this->assertFalse($table->getColumnByName('name')->getHidden());
        $this->assertTrue($table->getColumnByName('status')->getHidden());
    }

    public function testMaxRowsPerPage(): void
    {
        [$table, $api] = $this->apiTable();
        $table->setMaxRowsPerPage(5);

        $this->service()->getRows($table, new Request([], ['rowsPerPage' => '100']));
        $this->service()->getRows($table, new Request([], ['rowsPerPage' => '0']));

        $this->assertSame([5, 1], array_column($api->calls, 'limit'));
    }

    /**
     * @group known-bug
     */
    public function testRowsPerPageZeroThrowsDivisionByZero(): void
    {
        [$table] = $this->apiTable();

        $this->expectException(\DivisionByZeroError::class);
        $this->service()->getRows($table, new Request([], ['rowsPerPage' => '0']));
    }

    /**
     * @group known-bug
     *
     * Current behavior: multiple values are trimmed as a string.
     */
    public function testMultipleFilterValuesThrowATypeError(): void
    {
        [$table] = $this->apiTable();

        $this->expectException(\TypeError::class);
        $this->service()->getRows($table, new Request([], ['api_form' => ['status' => ['new', 'validated']]]));
    }

    /**
     * Before 3.5.0, the export called Table::haveTotalColumns(), which ApiTable does not have.
     */
    public function testExportAsCsv(): void
    {
        [$table, $api] = $this->apiTable();

        $csv = $this->service()->exportAsCsv($table, new Request([], ['api_form' => ['status' => 'new']]));

        $this->assertSame("name;status\nalpha;new\nbeta;validated\n", $csv);
        $this->assertSame([['filters' => ['status' => 'new'], 'orderBy' => [], 'page' => null, 'limit' => null]], $api->calls);
    }

    /**
     * @group known-bug
     *
     * Current behavior: a result without any row returns null rows, which the export counts.
     */
    public function testExportAsCsvWithoutRowsThrows(): void
    {
        $table = (new ApiTable())->setId('api')->setPath('/api')->setApi(new FakeApi([], 0, 0))->addColumn((new Column())->setName('name'));

        $this->assertNull($this->service()->getRows($table, new Request()));
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('count()');
        $this->service()->exportAsCsv($table, new Request());
    }

    public function testLoadRowsByIdMustBeOverridden(): void
    {
        [$table] = $this->apiTable();

        $this->expectExceptionMessage('this method should be overridden');
        $this->service()->loadRowsById($table, [1]);
    }

    private function service(): TableApiService
    {
        return static::getContainer()->get('kilik_table_api');
    }

    /**
     * @return array{ApiTable, FakeApi}
     */
    private function apiTable(): array
    {
        $api = new FakeApi(self::ROWS, 50, 20);
        $table = (new ApiTable())
            ->setId('api')
            ->setPath('/api')
            ->setApi($api)
            ->addColumn(
                (new Column())
                    ->setName('name')
                    ->setSort(['name' => 'asc', 'status' => 'DESC'])
                    ->setFilter((new Filter())->setName('name')->setField('name'))
            )
            ->addColumn((new Column())->setName('status')->setFilter((new Filter())->setName('status')->setField('status')));

        return [$table, $api];
    }
}
