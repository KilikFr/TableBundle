<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Components;

use Kilik\TableBundle\Components\Column;
use PHPUnit\Framework\TestCase;

final class ColumnSortDirectionTest extends TestCase
{
    public function testSortDirectionIsReturnedAsDefined(): void
    {
        $column = (new Column())->setSort(['a' => \SortDirection::Ascending, 'b' => 'desc']);

        $this->assertSame(['a' => \SortDirection::Ascending, 'b' => 'desc'], $column->getAutoSort(false));
    }

    public function testSortDirectionIsAutoInverted(): void
    {
        $column = (new Column())->setSort(['a' => \SortDirection::Ascending, 'b' => \SortDirection::Descending, 'c' => 'ASC']);

        $this->assertSame(
            ['a' => \SortDirection::Descending, 'b' => \SortDirection::Ascending, 'c' => 'desc'],
            $column->getAutoSort(true)
        );
    }
}
