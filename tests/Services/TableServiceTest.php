<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Services;

use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Services\TableService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TableServiceTest extends WebTestCase
{
    /**
     * @deprecated sould be replaced ASAP by Twig/Environment
     * @var \Twig_Environment
     */
    public static $twig;

    /**
     * @var FormFactory
     */
    public static $formFactory;

    public static function setUpBeforeClass(): void
    {
        // @deprecated
        static::$twig = new \Twig_Environment(new FilesystemLoader());
        // sould be replaced asap by the following line
        // static::$twig = new Environment(new FilesystemLoader());
        static::$formFactory = new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory()));
    }

    public function testConstruct()
    {
        $service = new TableService(static::$twig, static::$formFactory);

        $table = new Table();
        $table->setId('test');

        $column1 = new Column();
        $column1->setFilter((new Filter())->setName('column1'));
        $table->addColumn($column1);

        $form = $service->form($table);
        $this->assertEquals(3, $form->count(), 'should have 3 items: sortColumn,sortReverse, column1');
    }
}
