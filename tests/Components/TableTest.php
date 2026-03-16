<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Components;

use Kilik\TableBundle\Components\MassAction;
use Kilik\TableBundle\Components\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase
{
    public function testConstruct()
    {
        $table=new Table();

        $table->setId('myid');
        $this->assertEquals('myid',$table->getId());
        $this->assertEquals('kilik_myid_selected',$table->getSelectionFormKey());
    }

    public function testMassActionConstructor()
    {
        $massAction = new MassAction('name', 'label', 'css-class', '/action');

        $this->assertEquals('name', $massAction->getName());
        $this->assertEquals('label', $massAction->getLabel());
        $this->assertEquals('css-class', $massAction->getClass());
        $this->assertEquals('/action', $massAction->getAction());
    }

    public function testMassActionSetActionFluent()
    {
        $massAction = new MassAction('name');
        $result = $massAction->setAction('/new-action');

        $this->assertSame($massAction, $result);
        $this->assertEquals('/new-action', $massAction->getAction());
    }
}
