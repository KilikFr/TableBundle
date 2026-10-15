<?php

namespace Kilik\TableBundle\Tests\Integration;

use Doctrine\ORM\QueryBuilder;
use Kilik\TableBundle\Components\ApiTable;
use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Tests\Fixtures\Api\FakeApi;
use Symfony\Component\HttpFoundation\Request;

/**
 * Freezes where the services read their parameters from (table form, page, rowsPerPage, hiddenColumns).
 *
 * Each parameter is looked up in the request attributes, then the query string, then the body (POST),
 * the first bag containing the key wins, even with a null or empty value, and arrays are returned as is.
 */
final class RequestSourceTest extends IntegrationTestCase
{
    private const FORM = ['p_name' => 'apple', 'sortColumn' => 'p_name', 'sortReverse' => '1'];
    private const EXTRA = ['page' => '1', 'rowsPerPage' => '2', 'hiddenColumns' => ['p_name' => '1']];

    /**
     * Names containing "apple", sorted by name descending, first page of 2 rows.
     */
    private const EXPECTED_STATE = ['ids' => [1, 2], 'page' => 1, 'rowsPerPage' => 2, 'filteredRows' => 3, 'hidden' => true];

    public function testParametersFromTheBody(): void
    {
        $table = $this->table();
        $request = new Request([], array_merge(['products_form' => self::FORM], self::EXTRA));

        $this->assertSame(self::EXPECTED_STATE, $this->state($table, $request));
    }

    public function testParametersFromTheQueryString(): void
    {
        $table = $this->table();
        $request = new Request(array_merge(['products_form' => self::FORM], self::EXTRA));

        $this->assertSame(self::EXPECTED_STATE, $this->state($table, $request));
    }

    public function testParametersFromTheAttributes(): void
    {
        $table = $this->table();
        $request = new Request([], [], array_merge(['products_form' => self::FORM], self::EXTRA));

        $this->assertSame(self::EXPECTED_STATE, $this->state($table, $request));
    }

    public function testAttributesTakePrecedenceOverQueryAndBody(): void
    {
        $table = $this->table();
        $request = new Request(
            ['page' => '1', 'products_form' => self::FORM],
            ['page' => '1', 'rowsPerPage' => '2'],
            ['page' => '2'],
        );

        $this->assertSame([3], $this->state($table, $request)['ids']);
    }

    public function testQueryTakesPrecedenceOverBody(): void
    {
        $table = $this->table();
        $request = new Request(['page' => '2', 'rowsPerPage' => '2'], ['page' => '1', 'rowsPerPage' => '10', 'products_form' => self::FORM]);

        $state = $this->state($table, $request);
        $this->assertSame([3], $state['ids']);
        $this->assertSame(2, $state['rowsPerPage']);
    }

    public function testTableFormValuesAreNotMergedBetweenBags(): void
    {
        $table = $this->table();
        $request = new Request(['products_form' => ['sortColumn' => 'p_name', 'sortReverse' => '1']], [], ['products_form' => ['p_name' => 'apple']]);

        $this->assertSame([1, 2, 3], $this->state($table, $request)['ids']);
    }

    public function testNullAttributeHidesTheQueryValue(): void
    {
        $table = $this->table();
        $request = new Request(['page' => '2', 'rowsPerPage' => '2', 'products_form' => self::FORM], [], ['page' => null]);

        $this->assertSame(1, $this->state($table, $request)['page']);
    }

    public function testEmptyQueryValueHidesTheBodyValue(): void
    {
        $table = $this->table();
        $request = new Request(['page' => '', 'products_form' => self::FORM], ['page' => '2', 'rowsPerPage' => '2']);

        $this->assertSame(1, $this->state($table, $request)['page']);
    }

    public function testEachParameterIsResolvedIndependently(): void
    {
        $table = $this->table();
        $request = new Request(['page' => '2'], ['rowsPerPage' => '2', 'products_form' => self::FORM]);

        $this->assertSame([3], $this->state($table, $request)['ids']);
    }

    public function testScalarTableFormIsIgnored(): void
    {
        $table = $this->table();

        $this->assertSame(range(1, 10), $this->state($table, new Request(['products_form' => 'apple']))['ids']);
    }

    public function testJsonBodyIsNotRead(): void
    {
        $table = $this->table();
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['products_form' => self::FORM, 'page' => 2]));

        $state = $this->state($table, $request);
        $this->assertSame(range(1, 10), $state['ids']);
        $this->assertSame(25, $state['filteredRows']);
    }

    public function testMultipleFilterValuesFromTheQueryString(): void
    {
        $received = [];
        $filter = (new Filter())->setQueryPartBuilder(
            static function (Filter $filter, Table $table, QueryBuilder $queryBuilder, $values) use (&$received) {
                $received[] = $values;
                $queryBuilder->andWhere('p.status IN (:statuses)')->setParameter('statuses', $values);
            }
        );
        $table = $this->productTable($this->filteredColumn('p.status', $filter));

        $rows = $this->tableService()->getRows($table, new Request(['products_form' => ['p_status' => ['cancelled']]]));

        $this->assertSame([4, 6, 12, 19], self::productIds($rows));
        $this->assertSame([['cancelled'], ['cancelled']], $received);
    }

    public function testApiServiceUsesTheSameLookup(): void
    {
        $api = new FakeApi([], 30, 30);
        $table = (new ApiTable())
            ->setId('api')
            ->setPath('/api')
            ->setApi($api)
            ->addColumn((new Column())->setName('name')->setSort(['name' => 'asc'])->setFilter((new Filter())->setName('name')->setField('name')));
        $request = new Request(
            ['page' => '3', 'api_form' => ['name' => 'from query', 'sortColumn' => 'name']],
            ['page' => '1', 'rowsPerPage' => '5', 'api_form' => ['name' => 'from body'], 'hiddenColumns' => ['name' => '1']],
            ['page' => '2'],
        );

        $this->tableApiService()->getRows($table, $request);

        $this->assertSame([['filters' => ['name' => 'from query'], 'orderBy' => ['name' => 'asc'], 'page' => 2, 'limit' => 5]], $api->calls);
        $this->assertTrue($table->getColumnByName('name')->getHidden());
    }

    private function table(): Table
    {
        return $this->productTable($this->filteredColumn('p.name')->setSort(['p.name' => 'asc']));
    }

    /**
     * @return array{ids: list<int>, page: int, rowsPerPage: int, filteredRows: int, hidden: bool}
     */
    private function state(Table $table, Request $request): array
    {
        $rows = $this->tableService()->getRows($table, $request);

        return [
            'ids' => self::productIds($rows),
            'page' => $table->getPage(),
            'rowsPerPage' => $table->getRowsPerPage(),
            'filteredRows' => $table->getFilteredRows(),
            'hidden' => $table->getColumnByName('p_name')->getHidden(),
        ];
    }
}
