<?php

namespace Kilik\TableBundle\Tests\Integration;

use Kilik\TableBundle\Components\Table;

/**
 * Freezes how the hiddenColumns parameter is applied.
 */
final class HiddenColumnsTest extends IntegrationTestCase
{
    public function testColumnsAreHiddenByName(): void
    {
        $table = $this->table();

        $this->tableService()->getRows($table, $this->postRequest($table, [], ['hiddenColumns' => ['p_name' => '1', 'unknown' => '1']]));

        $this->assertSame(['p_id' => false, 'p_name' => true, 'p_status' => false], $this->hiddenStates($table));
    }

    public function testTheValueIsNotRead(): void
    {
        $table = $this->table();

        $this->tableService()->getRows($table, $this->postRequest($table, [], ['hiddenColumns' => ['p_name' => '0', 'p_status' => '']]));

        $this->assertSame(['p_id' => false, 'p_name' => true, 'p_status' => true], $this->hiddenStates($table));
    }

    /**
     * @dataProvider ignoredFormatProvider
     */
    public function testOtherFormatsAreIgnored($hiddenColumns): void
    {
        $table = $this->table();

        $this->tableService()->getRows($table, $this->postRequest($table, [], ['hiddenColumns' => $hiddenColumns]));

        $this->assertSame(['p_id' => false, 'p_name' => false, 'p_status' => false], $this->hiddenStates($table));
    }

    public function ignoredFormatProvider(): iterable
    {
        yield 'list of names' => [['p_name', 'p_status']];
        yield 'single name' => ['p_name'];
        yield 'empty' => [''];
    }

    public function testHiddenColumnFiltersAreStillApplied(): void
    {
        $table = $this->table();

        $rows = $this->tableService()->getRows($table, $this->postRequest($table, ['p_name' => 'apple'], ['hiddenColumns' => ['p_name' => '1']]));

        $this->assertSame([1, 2, 3], self::productIds($rows));
    }

    public function testHiddenByDefaultIsOnlyAnOptionForTheBrowser(): void
    {
        $table = $this->table();
        $table->getColumnByName('p_status')->setHiddenByDefault(true);

        $this->tableService()->getRows($table, $this->postRequest($table));

        $this->assertSame(['p_id' => false, 'p_name' => false, 'p_status' => false], $this->hiddenStates($table));
        $this->assertSame(['p_status'], $table->getHiddenColumnsNames());
    }

    private function table(): Table
    {
        return $this->productTable($this->filteredColumn('p.name'), $this->filteredColumn('p.status'));
    }

    /**
     * @return array<string, bool>
     */
    private function hiddenStates(Table $table): array
    {
        $states = [];
        foreach ($table->getColumns() as $column) {
            $states[$column->getName()] = $column->getHidden();
        }

        return $states;
    }
}
