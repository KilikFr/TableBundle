<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Tests\Fixtures\Entity\Product;
use Symfony\Component\HttpFoundation\Request;

/**
 * Number of SQL queries per scenario, compared with the budget stored in query-budget.php.
 *
 * The budget is an upper bound: lowering it after an optimization does not require changing this test.
 */
final class QueryCountTest extends IntegrationTestCase
{
    /**
     * @dataProvider scenarioProvider
     */
    public function testQueryCount(string $scenario): void
    {
        $budget = require __DIR__.'/query-budget.php';
        $this->assertArrayHasKey($scenario, $budget, 'Missing budget for this scenario.');

        $this->runScenario($scenario);

        $queries = static::$queryLogger->getQueries();
        $this->assertLessThanOrEqual(
            $budget[$scenario],
            \count($queries),
            sprintf("Scenario \"%s\" ran %d queries:\n%s", $scenario, \count($queries), implode("\n", $queries))
        );
    }

    public function scenarioProvider(): iterable
    {
        foreach (array_keys(require __DIR__.'/query-budget.php') as $scenario) {
            yield $scenario => [$scenario];
        }
    }

    private function runScenario(string $scenario): void
    {
        $service = $this->tableService();
        $nameColumn = fn () => $this->filteredColumn('p.name')->setSort(['p.name' => 'asc']);

        switch ($scenario) {
            case 'refresh_legacy':
                $table = $this->productTable($nameColumn());
                $service->getRows($table, $this->postRequest($table));
                break;
            case 'refresh_legacy_filtered_sorted':
                $table = $this->productTable($nameColumn());
                $service->getRows($table, $this->postRequest($table, ['p_name' => 'e', 'sortColumn' => 'p_name'], ['page' => '2', 'rowsPerPage' => '5']));
                break;
            case 'refresh_legacy_group_by':
                $table = $this->categoryTable();
                $service->getRows($table, $this->postRequest($table));
                break;
            case 'refresh_repository':
                $table = $this->productTable($nameColumn())->setEntityLoaderRepository(Product::class);
                $service->getRows($table, $this->postRequest($table));
                break;
            case 'refresh_callback':
                $table = $this->productTable($nameColumn())->setEntityLoaderCallback(
                    static fn (array $ids) => static::$em->getRepository(Product::class)->findBy(['id' => $ids])
                );
                $service->getRows($table, $this->postRequest($table));
                break;
            case 'refresh_without_entities':
                $table = $this->productTable($nameColumn())->setEntityLoaderMode(\Kilik\TableBundle\Components\Table::ENTITY_LOADER_NONE);
                $service->getRows($table, $this->postRequest($table));
                break;
            case 'refresh_identifier_count':
                $table = $this->productTable($nameColumn())->setDefaultIdentifierFieldNames();
                $service->getRows($table, $this->postRequest($table, ['p_name' => 'e']));
                break;
            case 'refresh_with_totals':
                $table = $this->categoryTable($this->totalColumn());
                $service->getRows($table, $this->postRequest($table));
                break;
            case 'handle_request':
                $table = $this->productTable($nameColumn());
                $service->handleRequest($table, $this->postRequest($table, ['p_name' => 'e']));
                break;
            case 'export_csv':
                $table = $this->productTable($nameColumn());
                $service->exportAsCsv($table, new Request([$table->getFormId() => ['p_name' => 'e']]));
                break;
            case 'export_csv_with_totals':
                $table = $this->categoryTable($this->totalColumn());
                $service->exportAsCsv($table, new Request());
                break;
            case 'selected_rows_repository':
                $table = $this->productTable()->setEntityLoaderRepository(Product::class);
                $service->getSelectedRows(new Request([], [$table->getSelectionFormKey() => ['1', '2']]), $table);
                break;
            default:
                $this->fail(sprintf('Unknown scenario "%s".', $scenario));
        }
    }

    private function totalColumn(): Column
    {
        return (new Column())
            ->setName('sumPrice')
            ->setFilter((new Filter())->setName('sumPrice')->setField('p.price'))
            ->useTotal();
    }
}
