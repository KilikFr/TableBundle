<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Components;

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
}
