<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\App;

use Kilik\TableBundle\KilikTableBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal application loading the bundle the way a Symfony application does.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new KilikTableBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/kilik-table-tests/'.md5(__DIR__.\PHP_VERSION.self::VERSION).'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/kilik-table-tests/'.md5(__DIR__.\PHP_VERSION.self::VERSION).'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $framework = [
            'secret' => 'kilik-table-tests',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'form' => ['enabled' => true],
            'translator' => ['enabled' => true, 'fallbacks' => ['en']],
            'assets' => ['enabled' => true],
        ];
        if (self::MAJOR_VERSION < 7) {
            $framework['annotations'] = ['enabled' => false];
        }
        if (self::VERSION_ID >= 70300 && class_exists(PropertyInfoExtractor::class)) {
            $framework['property_info'] = ['with_constructor_extractor' => true];
        }
        $container->extension('framework', $framework);

        $container->extension('twig', [
            'default_path' => '%kernel.project_dir%/templates',
            'strict_variables' => false,
        ]);

        $container->services()
            ->set(AutowiredServices::class)
            ->autowire()
            ->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
