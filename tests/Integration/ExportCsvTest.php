<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Tests\SnapshotTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    /**
     * @dataProvider streamedExportProvider
     */
    public function testStreamedExportIsTheSameAsTheExport(string $scenario): void
    {
        [$table, $request] = $this->exportScenario($scenario);
        $expected = $this->tableService()->exportAsCsv($table, $request);

        [$table, $request] = $this->exportScenario($scenario);
        $response = $this->tableService()->exportAsCsvResponse($table, $request);

        $this->assertSame($expected, self::sendContent($response));
    }

    public function streamedExportProvider(): iterable
    {
        yield 'filtered' => ['filtered'];
        yield 'sorted, with hidden columns' => ['sorted'];
        yield 'escaping' => ['escaping'];
        yield 'without result' => ['without result'];
        yield 'entities and scalars, with totals' => ['totals'];
        yield 'totals without result' => ['totals without result'];
    }

    public function testStreamedExportHeaders(): void
    {
        $table = $this->exportTable();

        $response = $this->tableService()->exportAsCsvResponse($table, $this->getRequest($table));
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=export.csv', $response->headers->get('Content-Disposition'));

        $response = $this->tableService()->exportAsCsvResponse($table, $this->getRequest($table), 'produits été.csv');
        $this->assertSame(
            'attachment; filename="produits _t_.csv"; filename*=utf-8\'\'produits%20%C3%A9t%C3%A9.csv',
            $response->headers->get('Content-Disposition')
        );
    }

    public function testStreamedExportCallbacksReceiveNoRows(): void
    {
        $calls = [];
        $table = $this->productTable(
            $this->filteredColumn('p.name')->setExportCallback(static function ($value, array $row, array $rows) use (&$calls) {
                $calls[] = [$value, $row['p_id'], $rows];

                return $value;
            })
        );

        self::sendContent($this->tableService()->exportAsCsvResponse($table, $this->getRequest($table, ['p_name' => 'apple'])));

        $this->assertSame([['red apple', 1, []], ['green apple', 2, []], ['apple pie', 3, []]], $calls);
    }

    public function testStreamedExportReadsTheRowsWhenTheResponseIsSent(): void
    {
        [$table, $request] = $this->exportScenario('totals');

        $response = $this->tableService()->exportAsCsvResponse($table, $request);
        // the totals are computed first
        $this->assertCount(1, static::$queryLogger->getQueries());

        self::sendContent($response);
        $this->assertCount(2, static::$queryLogger->getQueries());
    }

    /**
     * @return array{Table, Request}
     */
    private function exportScenario(string $scenario): array
    {
        switch ($scenario) {
            case 'filtered':
                $table = $this->exportTable();

                return [$table, $this->getRequest($table, ['p_status' => '==validated'])];
            case 'sorted':
                $table = $this->exportTable();

                return [$table, $this->getRequest($table, ['sortColumn' => 'p_name', 'sortReverse' => '1'], ['hiddenColumns' => ['p_name' => '1']])];
            case 'escaping':
                $table = $this->productTable(
                    (new Column())->setName('p_name')->setExportCallback(static fn ($value, array $row) => 1 === $row['p_id'] ? "semi;colon \"quoted\"\nline" : $value)
                );

                return [$table, $this->getRequest($table)];
            case 'without result':
                $table = $this->exportTable();

                return [$table, $this->getRequest($table, ['p_name' => 'nothing matches'])];
            case 'totals':
            case 'totals without result':
                $table = $this->categoryTable(
                    $this->filteredColumn('c.name')->setExportName('Category'),
                    (new Column())->setName('nbProducts'),
                    (new Column())->setName('sumPrice')->setFilter((new Filter())->setName('sumPrice')->setField('p.price'))->useTotal(),
                );

                return [$table, $this->getRequest($table, 'totals' === $scenario ? [] : ['c_name' => 'nothing matches'])];
        }

        throw new \LogicException(sprintf('Unknown scenario "%s".', $scenario));
    }

    private static function sendContent(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
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
