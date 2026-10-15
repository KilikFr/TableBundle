<?php

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Tests\SnapshotTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Freezes the CSV export (AbstractTableService::exportAsCsv()).
 */
final class ExportCsvTest extends IntegrationTestCase
{
    use SnapshotTrait;

    public function testExport(): void
    {
        $table = $this->exportTable();

        $csv = $this->tableService()->exportAsCsv($table, $this->getRequest($table, ['p_status' => '==validated']));

        $this->assertMatchesSnapshot($csv, 'validated.csv');
    }

    public function testExportWithoutResultIsEmpty(): void
    {
        $table = $this->exportTable();

        $this->assertSame('', $this->tableService()->exportAsCsv($table, $this->getRequest($table, ['p_name' => 'nothing matches'])));
    }

    public function testExportIgnoresPaginationAndKeepsSortAndHiddenColumns(): void
    {
        $table = $this->exportTable();
        $request = $this->getRequest(
            $table,
            ['sortColumn' => 'p_name', 'sortReverse' => '1'],
            ['page' => '2', 'rowsPerPage' => '5', 'hiddenColumns' => ['p_name' => '1']]
        );

        $csv = $this->tableService()->exportAsCsv($table, $request);

        $this->assertMatchesSnapshot($csv, 'all_sorted_by_name_desc.csv');
    }

    public function testExportFromAPostRequestIsTheSame(): void
    {
        $getTable = $this->exportTable();
        $postTable = $this->exportTable();

        $this->assertSame(
            $this->tableService()->exportAsCsv($getTable, $this->getRequest($getTable, ['p_status' => '==validated'])),
            $this->tableService()->exportAsCsv($postTable, $this->postRequest($postTable, ['p_status' => '==validated']))
        );
    }

    public function testExportEscaping(): void
    {
        $values = [
            1 => 'semi;colon',
            2 => 'double "quote"',
            3 => "new\nline",
            4 => 'back\\slash "quoted"',
            5 => 'tab	char',
            6 => '',
        ];
        $table = $this->productTable(
            (new Column())->setName('p_name')->setExportCallback(static fn ($value, array $row) => $values[$row['p_id']] ?? $value)
        );

        $csv = $this->tableService()->exportAsCsv($table, $this->getRequest($table));

        $this->assertMatchesSnapshot($csv, 'escaping.csv');
    }

    public function testExportCallbackReceivesValueRowAndRows(): void
    {
        $calls = [];
        $table = $this->productTable(
            $this->filteredColumn('p.name')->setExportCallback(static function ($value, array $row, array $rows) use (&$calls) {
                $calls[] = [$value, $row['p_id'], \count($rows)];

                return $value;
            })
        );

        $this->tableService()->exportAsCsv($table, $this->getRequest($table, ['p_name' => 'apple']));

        $this->assertSame([['red apple', 1, 3], ['green apple', 2, 3], ['apple pie', 3, 3]], $calls);
    }

    public function testExportWithTotals(): void
    {
        $table = $this->categoryTable(
            (new Column())->setName('c_name')->setExportName('Category'),
            (new Column())->setName('nbProducts'),
            (new Column())
                ->setName('sumPrice')
                ->setFilter((new Filter())->setName('sumPrice')->setField('p.price'))
                ->useTotal(),
        );

        $csv = $this->tableService()->exportAsCsv($table, $this->getRequest($table));

        $this->assertMatchesSnapshot($csv, 'categories_with_totals.csv');
    }

    public function testExportOnlyRunsTheDataQuery(): void
    {
        $table = $this->exportTable();

        $this->tableService()->exportAsCsv($table, $this->getRequest($table));

        $this->assertCount(1, static::$queryLogger->getQueries());
    }

    private function exportTable(): Table
    {
        return $this->productTable(
            $this->filteredColumn('p.name')->setExportName('Name')->setSort(['p.name' => 'asc']),
            $this->filteredColumn('p.status')->setExportCallback(static fn ($value) => strtoupper($value)),
            (new Column())->setName('p_createdAt')->setDisplayFormat(Column::FORMAT_DATE)->setDisplayFormatParams('d/m/Y'),
            (new Column())->setName('p_price')->setDisplayFormat(Column::FORMAT_DATE),
            (new Column())->setName('p_description'),
        );
    }

    /**
     * Request with parameters in the query string, as for a download link.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $extra
     */
    private function getRequest(Table $table, array $form = [], array $extra = []): Request
    {
        return new Request(array_merge([$table->getFormId() => $form], $extra));
    }
}
