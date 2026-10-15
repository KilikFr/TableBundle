<?php

namespace Kilik\TableBundle\Tests\Bundle;

use Kilik\TableBundle\DependencyInjection\Configuration;
use Kilik\TableBundle\DependencyInjection\KilikTableExtension;
use Kilik\TableBundle\KilikTableBundle;
use Kilik\TableBundle\Services\TableApiService;
use Kilik\TableBundle\Services\TableService;
use Kilik\TableBundle\Tests\App\AutowiredServices;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Freezes how the bundle integrates into a Symfony application.
 */
final class KilikTableBundleTest extends KernelTestCase
{
    private const SRC_DIR = __DIR__.'/../../src';

    public function testPublicServices(): void
    {
        $container = static::bootKernel()->getContainer();

        $this->assertInstanceOf(TableService::class, $container->get('kilik_table'));
        $this->assertInstanceOf(TableApiService::class, $container->get('kilik_table_api'));
        $this->assertNotSame($container->get('kilik_table'), $container->get('kilik_table_api'));
    }

    public function testServicesCanBeAutowired(): void
    {
        $container = static::bootKernel()->getContainer();

        $services = $container->get(AutowiredServices::class);

        $this->assertSame($container->get('kilik_table'), $services->tableService);
        $this->assertSame($container->get('kilik_table_api'), $services->tableApiService);
    }

    public function testBundleMetadata(): void
    {
        $bundle = static::bootKernel()->getBundle('KilikTableBundle');

        $this->assertInstanceOf(KilikTableBundle::class, $bundle);
        $this->assertSame(realpath(self::SRC_DIR), realpath($bundle->getPath()));
        $this->assertInstanceOf(KilikTableExtension::class, $bundle->getContainerExtension());
        $this->assertSame('kilik_table', $bundle->getContainerExtension()->getAlias());
    }

    public function testConfigurationIsEmpty(): void
    {
        $this->assertSame([], (new Processor())->processConfiguration(new Configuration(), [[]]));

        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['unknown' => true]]);
    }

    public function testTemplatesAreAvailableInTheTwigNamespace(): void
    {
        $loader = static::getContainer()->get('twig')->getLoader();

        $files = (new Finder())->files()->in(self::SRC_DIR.'/Resources/views')->name('*.twig');
        $this->assertGreaterThan(0, \count($files));
        foreach ($files as $file) {
            $name = '@KilikTable/'.str_replace('\\', '/', $file->getRelativePathname());
            $this->assertTrue($loader->exists($name), $name);
        }
    }

    /**
     * @dataProvider localeProvider
     */
    public function testTranslations(string $locale): void
    {
        $translator = static::getContainer()->get('translator');
        $messages = Yaml::parseFile(self::SRC_DIR.'/Resources/translations/messages.'.$locale.'.yml');

        $this->assertNotEmpty($messages['kiliktable']);
        foreach ($messages['kiliktable'] as $key => $message) {
            $this->assertSame($message, $translator->trans('kiliktable.'.$key, [], 'messages', $locale), $key);
        }
    }

    public function localeProvider(): iterable
    {
        foreach (['de', 'en', 'es', 'fr', 'nl'] as $locale) {
            yield $locale => [$locale];
        }
    }

    public function testAssetsInstall(): void
    {
        $kernel = static::bootKernel();
        $target = sys_get_temp_dir().'/kilik-table-assets-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($target);

        try {
            $tester = new CommandTester((new Application($kernel))->find('assets:install'));
            $tester->execute(['target' => $target]);

            $tester->assertCommandIsSuccessful();
            foreach (['js/KilikTable.js', 'css/KilikTable.css'] as $asset) {
                $this->assertFileEquals(self::SRC_DIR.'/Resources/public/'.$asset, $target.'/bundles/kiliktable/'.$asset);
            }
        } finally {
            (new Filesystem())->remove($target);
        }
    }
}
