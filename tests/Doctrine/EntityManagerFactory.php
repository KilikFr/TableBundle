<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Doctrine;

use Composer\InstalledVersions;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * Builds an EntityManager for the test entities without DoctrineBundle,
 * using only APIs that do not trigger deprecations on the supported ORM/DBAL versions.
 */
final class EntityManagerFactory
{
    public const DEFAULT_URL = 'sqlite:///:memory:';

    public static function create(QueryLogger $logger, ?string $url = null): EntityManager
    {
        $url = $url ?? (getenv('KILIK_TEST_DATABASE_URL') ?: self::DEFAULT_URL);

        if (!Type::hasType('uuid')) {
            Type::addType('uuid', UuidType::class);
        }

        $dbalConfig = new DbalConfiguration();
        if (method_exists($dbalConfig, 'setSchemaManagerFactory')) {
            $dbalConfig->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        }
        $dbalConfig->setMiddlewares([new LoggingMiddleware($logger)]);

        $connection = DriverManager::getConnection(self::connectionParams($url), $dbalConfig);

        // passing the event manager avoids the deprecated Connection::getEventManager() (ORM 2.x on DBAL 3)
        return new EntityManager($connection, self::ormConfiguration(), new EventManager());
    }

    public static function createSchema(EntityManager $em): void
    {
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    /**
     * @return array<string, mixed>
     */
    private static function connectionParams(string $url): array
    {
        if (self::DEFAULT_URL === $url) {
            return ['driver' => 'pdo_sqlite', 'memory' => true];
        }

        return (new DsnParser([
            'sqlite' => 'pdo_sqlite',
            'mysql' => 'pdo_mysql',
            'postgresql' => 'pdo_pgsql',
            'postgres' => 'pdo_pgsql',
            'pgsql' => 'pdo_pgsql',
        ]))->parse($url);
    }

    private static function ormConfiguration(): \Doctrine\ORM\Configuration
    {
        $paths = [\dirname(__DIR__).'/Fixtures/Entity'];

        if (str_starts_with((string) InstalledVersions::getVersion('doctrine/orm'), '2.')) {
            // ORM 2.x: opt into the ORM 3 behaviors to avoid their deprecations
            $config = ORMSetup::createConfiguration(true);
            $config->setMetadataDriverImpl(new AttributeDriver($paths, true));
            $config->setLazyGhostObjectEnabled(true);

            return $config;
        }

        if (\PHP_VERSION_ID >= 80400 && method_exists(ORMSetup::class, 'createAttributeMetadataConfig')) {
            // ORM >= 3.5 on PHP >= 8.4: native lazy objects, no proxy directory
            $config = ORMSetup::createAttributeMetadataConfig($paths, true);
        } else {
            $config = ORMSetup::createAttributeMetadataConfiguration($paths, true);
        }

        if (\PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        return $config;
    }
}
