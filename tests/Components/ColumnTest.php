<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Components;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Table;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    public function testConstruct()
    {
        $column = new Column();

        $column->setLabel('mylabel');
        $this->assertEquals('mylabel', $column->getLabel());

        $column->setName('myname');
        $this->assertEquals('myname', $column->getName());

        $column->setSort(['field1' => 'ASC', 'field2' => 'ASC']);
        $this->assertEquals(['field1' => 'ASC', 'field2' => 'ASC'], $column->getSort());

        $column->setSortReverse(['field2' => 'DESC', 'field1' => 'DESC']);
        $this->assertEquals(['field2' => 'DESC', 'field1' => 'DESC'], $column->getSortReverse());

        $this->assertEquals(false, $column->getTranslateLabel());
        $this->assertNull($column->getTranslateDomain());
        $column->setTranslateLabel(true);
        $this->assertEquals(true, $column->getTranslateLabel());
        $this->assertEquals('messages', $column->getTranslateDomain());
        $column->setTranslateDomain('other_domain');
        $this->assertEquals('other_domain', $column->getTranslateDomain());

        $this->assertEquals(false, $column->getRaw());
        $column->setRaw(true);
        $this->assertEquals(true, $column->getRaw());

        $this->assertEquals('text', $column->getDisplayFormat());
        $column->setDisplayFormat('date');
        $this->assertEquals('date', $column->getDisplayFormat());
    }

    /**
     * @dataProvider getValueProvider
     */
    public function testGetValue($wanted, Column $column)
    {
        $row = [
            'field1' => 'value1',
            'field2' => 'value2',
            'field3' => '03/08/2020',
            'field4' => null,
            'field5' => '<h1>test</h1>',
            'field6' => new \DateTime('2020-08-03 11:34:00'),
        ];

        $rows = [
            [
                'field1' => 'value1',
                'field2' => 'value2',
                'field3' => '03/08/2020',
                'field4' => null,
                'field5' => '<h1>test</h1>',
                'field6' => new \DateTime('2020-08-03 11:34:00'),
            ],
            [
                'field1' => 'value11',
                'field2' => 'value12',
                'field3' => '04/08/2020',
                'field4' => null,
                'field5' => '<h1>test2</h1>',
                'field6' => new \DateTime('2020-09-04 20:15:45'),
            ],
        ];

        $this->assertEquals($wanted, $column->getValue($row, $rows));
    }

    public function getValueProvider()
    {
        return [
            [
                'value1',
                (new Column())->setName('field1'),
            ],
            [
                'value2',
                (new Column())->setName('field2'),
            ],
            [
                '03/08/2020',
                (new Column())->setName('field3'),
            ],
            [
                null,
                (new Column())->setName('field4'),
            ],
            [
                '<h1>test</h1>',
                (new Column())->setName('field5'),
            ],
            [
                'VALUE1',
                (new Column())->setName('field1')->setDisplayCallback(function ($value, $row, $rows) { return strtoupper($value); }),
            ],
            [
                '2020-08-03 11:34:00',
                (new Column())->setName('field6')->setDisplayFormat('date'),
            ],
            [
                '03/08/2020',
                (new Column())->setName('field6')->setDisplayFormat('date')->setDisplayFormatParams('d/m/Y'),
            ],
        ];
    }
}
