<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Twig;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\FilterCheckbox;
use Kilik\TableBundle\Components\FilterDate;
use Kilik\TableBundle\Components\FilterSelect;
use Kilik\TableBundle\Components\MassAction;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Services\TableService;
use Kilik\TableBundle\Tests\Integration\HandleRequestTest;
use Kilik\TableBundle\Tests\SnapshotTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Twig\Environment;
use Twig\Source;

/**
 * Lints the bundle templates and freezes the initial rendering of the table templates.
 */
final class TemplatesTest extends KernelTestCase
{
    use SnapshotTrait;

    private const VIEWS_DIR = __DIR__.'/../../src/Resources/views';

    /**
     * @dataProvider templateFileProvider
     */
    public function testLint(string $name): void
    {
        $twig = $this->twig();
        $source = $twig->getLoader()->getSourceContext($name);

        $twig->parse($twig->tokenize(new Source($source->getCode(), $name, $source->getPath())));

        $this->addToAssertionCount(1);
    }

    public function templateFileProvider(): iterable
    {
        $files = (new Finder())->files()->in(self::VIEWS_DIR)->name('*.twig')->sortByName();
        foreach ($files as $file) {
            $name = '@KilikTable/'.str_replace('\\', '/', $file->getRelativePathname());
            yield $name => [$name];
        }
    }

    /**
     * @dataProvider tableTemplateProvider
     */
    public function testInitialRendering(string $template): void
    {
        $table = $this->createTable($template);

        $html = $this->twig()->render($template, ['table' => $table]);

        $this->assertMatchesSnapshot($html, str_replace(['@KilikTable/', '/', '.twig'], ['', '_', ''], $template));
    }

    /**
     * Strict variables are enabled in the debug mode of most applications.
     *
     * @dataProvider tableTemplateProvider
     */
    public function testRenderingWithStrictVariables(string $template): void
    {
        $twig = $this->twig();
        $table = $this->createTable($template);
        $twig->enableStrictVariables();

        try {
            $html = $twig->render($template, ['table' => $table]);
        } finally {
            $twig->disableStrictVariables();
        }

        $this->assertSame($this->twig()->render($template, ['table' => $this->createTable($template)]), $html);
    }

    public function tableTemplateProvider(): iterable
    {
        foreach (HandleRequestTest::TABLE_TEMPLATES as $template) {
            yield $template => [$template];
        }
    }

    public function testLayout(): void
    {
        $this->assertMatchesSnapshot($this->twig()->render('@KilikTable/layout.html.twig'), 'layout.html');
    }

    private function twig(): Environment
    {
        return static::getContainer()->get('twig');
    }

    private function createTable(string $template): Table
    {
        $delete = new MassAction('delete', 'Delete selection', 'fa fa-trash');
        $delete->setAction('/products/delete');

        $table = (new Table())
            ->setId('products')
            ->setPath('/products/list')
            ->setTemplate($template)
            ->setRowsPerPage(20)
            ->setRowsPerPageOptions([10, 20, 50])
            ->addCustomOption('askForReloadDelay', 500)
            ->addMassAction($delete)
            ->addMassAction(new MassAction('export', 'Export', 'fa fa-download', '/ignored'))
            ->addColumn((new Column())->setLabel('Id')->setName('p_id')->setSort(['p.id' => 'asc']))
            ->addColumn(
                (new Column())
                    ->setLabel('Name')
                    ->setSort(['p.name' => 'asc'])
                    ->setHeaderClass('name-header')
                    ->setFilterClass('name-filter')
                    ->setFilter((new Filter())->setName('p_name')->setField('p.name')->setDefaultValue('apple'))
            )
            ->addColumn(
                (new Column())
                    ->setLabel('kiliktable.show')
                    ->setTranslateLabel(true)
                    ->setFilter(
                        (new FilterSelect())
                            ->setName('p_status')
                            ->setField('p.status')
                            ->setChoices(['New' => 'new', 'Validated' => 'validated'])
                            ->setPlaceholder('All')
                    )
            )
            ->addColumn((new Column())->setLabel('Out of stock')->setFilter((new FilterCheckbox())->setName('p_out')->setField('p.quantity')))
            ->addColumn(
                (new Column())
                    ->setLabel('Created')
                    ->setHiddenByDefault(true)
                    ->setFilter((new FilterDate())->setName('p_createdAt')->setField('p.createdAt'))
            )
            ->addColumn((new Column())->setLabel('Description')->setName('p_description')->setCapitalize(false))
            ->addFilter((new Filter())->setName('search')->setField('p.description'));

        /** @var TableService $service */
        $service = static::getContainer()->get('kilik_table');
        $service->createFormView($table);

        return $table;
    }
}
