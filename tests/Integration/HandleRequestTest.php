<?php

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\ApiTable;
use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\FilterSelect;
use Kilik\TableBundle\Components\MassAction;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Tests\Fixtures\Api\FakeApi;
use Kilik\TableBundle\Tests\SnapshotTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Freezes the JSON response returned to KilikTable.js (AbstractTableService::handleRequest()).
 *
 * Snapshots store the decoded response (scalars, then each HTML block); the encoding itself is checked by
 * assertJsonResponse(), so that the raw response body is fully determined.
 */
final class HandleRequestTest extends IntegrationTestCase
{
    use SnapshotTrait;

    public const TABLE_TEMPLATES = [
        '@KilikTable/_defaultTable.html.twig',
        '@KilikTable/_defaultTableAlt.html.twig',
        '@KilikTable/_defaultTableSimple.html.twig',
        '@KilikTable/_condensedTable.html.twig',
        '@KilikTable/_formLeftNoTable.html.twig',
        '@KilikTable/theme/dark4/tables/default.html.twig',
        '@KilikTable/theme/dark4/tables/alternative.html.twig',
    ];

    private const KEYS = ['page', 'rowsPerPage', 'totalRows', 'filteredRows', 'lastPage', 'tableBody', 'tableFoot', 'tableStats', 'tablePagination'];

    public function testFirstPage(): void
    {
        $table = $this->richTable();

        $response = $this->tableService()->handleRequest($table, $this->postRequest($table));

        $this->assertJsonResponse($response);
        $this->assertMatchesSnapshot($this->readable($response), 'first_page.txt');
    }

    public function testFilteredSortedPageWithHiddenColumn(): void
    {
        $table = $this->richTable();
        $request = $this->postRequest(
            $table,
            ['p_name' => 'e', 'sortColumn' => 'p_name', 'sortReverse' => '1'],
            ['page' => '2', 'rowsPerPage' => '5', 'hiddenColumns' => ['p_description' => '1']]
        );

        $response = $this->tableService()->handleRequest($table, $request);

        $this->assertJsonResponse($response);
        $this->assertMatchesSnapshot($this->readable($response), 'filtered_sorted_page.txt');
    }

    public function testEmptyResult(): void
    {
        $table = $this->richTable();

        $response = $this->tableService()->handleRequest($table, $this->postRequest($table, ['p_name' => 'nothing matches']));

        $this->assertJsonResponse($response);
        $this->assertMatchesSnapshot($this->readable($response), 'empty.txt');
    }

    public function testMassActions(): void
    {
        $table = $this->richTable();
        $delete = new MassAction('delete', 'Delete selection', 'fa fa-trash');
        $delete->setAction('/products/delete');
        $table->addMassAction($delete)->addMassAction(new MassAction('export', 'Export', 'fa fa-download', '/ignored'));

        $response = $this->tableService()->handleRequest($table, $this->postRequest($table, [], ['rowsPerPage' => '5']));

        $this->assertJsonResponse($response);
        $this->assertMatchesSnapshot($this->readable($response), 'mass_actions.txt');
    }

    public function testTotals(): void
    {
        $table = $this->categoryTable(
            (new Column())->setName('c_name')->setLabel('Category'),
            (new Column())->setName('nbProducts')->setLabel('Products'),
            (new Column())
                ->setName('sumPrice')
                ->setLabel('Total price')
                ->setFilter((new Filter())->setName('sumPrice')->setField('p.price'))
                ->useTotal(),
        );

        $response = $this->tableService()->handleRequest($table, $this->postRequest($table));

        $this->assertJsonResponse($response);
        $this->assertMatchesSnapshot($this->readable($response), 'totals.txt');
    }

    public function testTemplateParamsAreOnlyPassedToTheBody(): void
    {
        $table = $this->productTable()
            ->setTemplate('custom_table.html.twig')
            ->setTemplateParams(['foo' => 'bar']);

        $data = json_decode($this->tableService()->handleRequest($table, $this->postRequest($table))->getContent(), true);

        $this->assertStringContainsString('custom body, foo=bar, rows=10', $data['tableBody']);
        $this->assertStringContainsString('custom foot, foo=none', $data['tableFoot']);
    }

    public function testCustomCellTemplate(): void
    {
        $table = $this->productTable((new Column())->setName('p_name')->setCellTemplate('custom_cell.html.twig'));

        $data = json_decode($this->tableService()->handleRequest($table, $this->postRequest($table, [], ['rowsPerPage' => '2']))->getContent(), true);

        $this->assertStringContainsString('<td class="custom-cell" data-column="p_name">RED APPLE #1</td>', $data['tableBody']);
        $this->assertStringContainsString('<td class="custom-cell" data-column="p_name">GREEN APPLE #2</td>', $data['tableBody']);
    }

    /**
     * @dataProvider templateProvider
     */
    public function testBundledTemplates(string $template): void
    {
        $table = $this->richTable()->setTemplate($template);
        $delete = new MassAction('delete', 'Delete selection');
        $delete->setAction('/products/delete');
        $table->addMassAction($delete);
        $request = $this->postRequest($table, ['sortColumn' => 'p_name'], ['rowsPerPage' => '3', 'page' => '2']);

        if (\in_array($template, ['@KilikTable/_condensedTable.html.twig', '@KilikTable/_formLeftNoTable.html.twig'], true)) {
            // known bug: these templates have no "tableFoot" block, required by handleRequest()
            $this->expectException(\Twig\Error\RuntimeError::class);
            $this->expectExceptionMessage('Block "tableFoot"');
        }

        $response = $this->tableService()->handleRequest($table, $request);

        $this->assertJsonResponse($response);
        $this->assertMatchesSnapshot($this->readable($response), 'template_'.str_replace(['@KilikTable/', '/', '.html.twig'], ['', '_', ''], $template).'.txt');
    }

    public function templateProvider(): iterable
    {
        foreach (self::TABLE_TEMPLATES as $template) {
            yield $template => [$template];
        }
    }

    public function testApiTable(): void
    {
        $table = (new ApiTable())
            ->setId('api')
            ->setPath('/api')
            ->setApi(new FakeApi([['name' => 'alpha'], ['name' => '<b>beta</b>']], 12, 2))
            ->addColumn((new Column())->setName('name')->setLabel('Name')->setFilter((new Filter())->setName('name')->setField('name')));

        $response = $this->tableApiService()->handleRequest($table, new Request());

        $this->assertJsonResponse($response);
        $this->assertMatchesSnapshot($this->readable($response), 'api_table.txt');
    }

    /**
     * Products table using most column options.
     */
    private function richTable(): Table
    {
        return $this->productTable(
            $this->filteredColumn('p.name')
                ->setLabel('Name')
                ->setSort(['p.name' => 'asc'])
                ->setDisplayClass('name-cell')
                ->setHeaderClass('name-header')
                ->setFilterClass('name-filter'),
            $this->filteredColumn('p.status', (new FilterSelect())->setChoices(['New' => 'new', 'Validated' => 'validated', 'Cancelled' => 'cancelled']))
                ->setLabel('kiliktable.show')
                ->setTranslateLabel(true),
            (new Column())
                ->setName('p_price')
                ->setLabel('Price')
                ->setDisplayCallback(static fn ($value, array $row, array $rows) => sprintf('%.2f EUR (%d rows)', $value / 100, \count($rows))),
            (new Column())->setName('p_description')->setLabel('Description')->setDisplayCallback(static fn ($value) => null === $value ? '<i>none</i>' : $value)->setRaw(true),
            (new Column())->setName('p_createdAt')->setLabel('Created')->setDisplayFormat(Column::FORMAT_DATE)->setDisplayFormatParams('d/m/Y')->setHiddenByDefault(true),
        );
    }

    private function assertJsonResponse(Response $response): void
    {
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame(JsonResponse::DEFAULT_ENCODING_OPTIONS, $response->getEncodingOptions());

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(self::KEYS, array_keys($data));
        foreach (['page', 'rowsPerPage', 'totalRows', 'filteredRows', 'lastPage'] as $key) {
            $this->assertIsInt($data[$key], $key);
        }
        foreach (['tableBody', 'tableFoot', 'tableStats', 'tablePagination'] as $key) {
            $this->assertIsString($data[$key], $key);
        }
        $this->assertSame(json_encode($data, JsonResponse::DEFAULT_ENCODING_OPTIONS), $response->getContent());
    }

    private function readable(Response $response): string
    {
        $output = '';
        foreach (json_decode($response->getContent(), true) as $key => $value) {
            $output .= \is_string($value) ? "===== {$key} =====\n{$value}\n" : "{$key}: {$value}\n";
        }

        return $output;
    }
}
