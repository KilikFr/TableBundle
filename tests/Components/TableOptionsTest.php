<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Components;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\MassAction;
use Kilik\TableBundle\Components\Table;
use PHPUnit\Framework\TestCase;

/**
 * Freezes the table values used by the templates and KilikTable.js.
 */
final class TableOptionsTest extends TestCase
{
    public function testOptionsForTheBrowser(): void
    {
        $table = (new Table())
            ->setRowsPerPage(25)
            ->setSkipLoadFilterFromLocalStorage(true)
            ->addCustomOption('askForReloadDelay', 500)
            ->addCustomOption('rowsPerPage', 999)
            ->addColumn((new Column())->setName('a')->setHiddenByDefault(true))
            ->addColumn((new Column())->setName('b'));

        $this->assertSame(
            [
                'askForReloadDelay' => 500,
                'rowsPerPage' => 25,
                'defaultHiddenColumns' => ['a'],
                'skipLoadFromLocalStorage' => false,
                'skipLoadFilterFromLocalStorage' => true,
            ],
            $table->getOptions()
        );
    }

    public function testIdentifiers(): void
    {
        $table = (new Table())->setId('products');

        $this->assertSame('products_form', $table->getFormId());
        $this->assertSame('products_body', $table->getBodyId());
        $this->assertSame('products_foot', $table->getFootId());
        $this->assertSame('kilik_products_selected', $table->getSelectionFormKey());
    }

    public function testDefaults(): void
    {
        $table = new Table();

        $this->assertSame(10, $table->getRowsPerPage());
        $this->assertSame([5, 10, 20, 50, 100], $table->getRowsPerPageOptions());
        $this->assertSame('@KilikTable/_defaultTable.html.twig', $table->getTemplate());
        $this->assertSame([], $table->getTemplateParams());
        $this->assertNull($table->getIdentifierFieldNames());
    }

    /**
     * @group known-bug
     *
     * Current behavior: the $action constructor argument is ignored and setAction() is not fluent.
     */
    public function testMassActionConstructorIgnoresTheAction(): void
    {
        $massAction = new MassAction('delete', 'Delete', 'btn', '/delete');

        $this->assertNull($massAction->getAction());
        $this->assertNull($massAction->setAction('/delete'));
        $this->assertSame('/delete', $massAction->getAction());
    }
}
