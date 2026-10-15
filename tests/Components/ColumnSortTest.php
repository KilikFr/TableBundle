<?php

namespace Kilik\TableBundle\Tests\Components;

use Kilik\TableBundle\Components\Column;
use PHPUnit\Framework\TestCase;

/**
 * Freezes the sort definitions returned by Column.
 */
final class ColumnSortTest extends TestCase
{
    public function testSortIsReturnedAsDefined(): void
    {
        $column = (new Column())->setSort(['p.name' => 'ASC', 'p.id' => 'desc']);

        $this->assertSame(['p.name' => 'ASC', 'p.id' => 'desc'], $column->getAutoSort(false));
        $this->assertSame(['p.name' => 'ASC', 'p.id' => 'desc'], $column->getSort());
    }

    public function testReverseSortIsAutoInvertedAndLowercased(): void
    {
        $column = (new Column())->setSort(['a' => 'ASC', 'b' => 'desc', 'c' => 'Asc', 'd' => 'other']);

        $expected = ['a' => 'desc', 'b' => 'asc', 'c' => 'desc', 'd' => 'asc'];
        $this->assertSame($expected, $column->getAutoSort(true));
        $this->assertSame($expected, $column->getAutoInvertedSort());
    }

    public function testExplicitReverseSort(): void
    {
        $column = (new Column())->setSort(['a' => 'asc'])->setSortReverse(['b' => 'DESC']);

        $this->assertSame(['b' => 'DESC'], $column->getAutoSort(true));
        $this->assertSame(['b' => 'DESC'], $column->getSortReverse());
    }

    public function testSortable(): void
    {
        $this->assertFalse((new Column())->sortable());
        $this->assertNull((new Column())->getAutoSort(false));
        $this->assertTrue((new Column())->setSort(['a' => 'asc'])->sortable());
        $this->assertTrue((new Column())->setSortReverse(['a' => 'desc'])->sortable());
    }
}
