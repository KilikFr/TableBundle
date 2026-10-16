<?php

declare(strict_types=1);

/*
 * Measures SQL queries, time and memory of the main TableService scenarios on a generated data set.
 *
 * Usage: php tests/Benchmark/benchmark.php [--rows=10000] [--runs=7] [--json=report.json]
 * The database is selected with KILIK_TEST_DATABASE_URL (in-memory SQLite by default).
 *
 * Time is the median of the runs (after a warm-up run), memory is the peak memory used by one run.
 * Deprecation notices are counted instead of being displayed, so that their output does not distort the timings.
 */

use Doctrine\ORM\EntityManager;
use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Services\TableService;
use Kilik\TableBundle\Tests\App\TestKernel;
use Kilik\TableBundle\Tests\Doctrine\EntityManagerFactory;
use Kilik\TableBundle\Tests\Doctrine\QueryLogger;
use Kilik\TableBundle\Tests\Fixtures\Entity\Category;
use Kilik\TableBundle\Tests\Fixtures\Entity\Product;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$options = getopt('', ['rows:', 'runs:', 'json:']) + ['rows' => 10000, 'runs' => 7];
$rowsCount = (int) $options['rows'];
$runs = max(1, (int) $options['runs']);

$deprecations = 0;
set_error_handler(static function (int $type) use (&$deprecations): bool {
    if (\E_DEPRECATED === $type || \E_USER_DEPRECATED === $type) {
        ++$deprecations;

        return true;
    }

    return false;
});

$logger = new QueryLogger();
$em = EntityManagerFactory::create($logger);
EntityManagerFactory::createSchema($em);
generateData($em, $rowsCount);

$kernel = new TestKernel('test', false);
$kernel->boot();
/** @var TableService $service */
$service = $kernel->getContainer()->get('kilik_table');

$productTable = static function (Column ...$columns) use ($em): Table {
    $table = (new Table())
        ->setId('products')
        ->setPath('/products')
        ->setQueryBuilder($em->createQueryBuilder()->select('p')->from(Product::class, 'p'), 'p')
        ->addColumn((new Column())->setName('p_id'))
        ->addColumn((new Column())->setName('p_name')->setSort(['p.name' => 'asc'])->setFilter((new Filter())->setName('p_name')->setField('p.name')))
        ->addColumn((new Column())->setName('p_status')->setFilter((new Filter())->setName('p_status')->setField('p.status')))
        ->addColumn((new Column())->setName('p_price'));
    foreach ($columns as $column) {
        $table->addColumn($column);
    }

    return $table;
};
$categoryTable = static function (bool $withTotals = true) use ($em): Table {
    $queryBuilder = $em->createQueryBuilder()
        ->select('c', 'COUNT(p.id) AS nbProducts', 'SUM(p.price) AS sumPrice')
        ->from(Category::class, 'c')
        ->leftJoin('c.products', 'p')
        ->groupBy('c.id');

    $sumColumn = (new Column())->setName('sumPrice')->setFilter((new Filter())->setName('sumPrice')->setField('p.price'));

    return (new Table())
        ->setId('categories')
        ->setPath('/categories')
        ->setQueryBuilder($queryBuilder, 'c')
        ->addColumn((new Column())->setName('c_name'))
        ->addColumn($withTotals ? $sumColumn->useTotal() : $sumColumn);
};
$post = static fn (Table $table, array $form = [], array $extra = []) => new Request([], array_merge([$table->getFormId() => $form], ['rowsPerPage' => '50'], $extra));

$scenarios = [
    'refresh_legacy' => static function () use ($service, $productTable, $post) {
        $table = $productTable();
        $service->getRows($table, $post($table));
    },
    'refresh_legacy_filtered' => static function () use ($service, $productTable, $post) {
        $table = $productTable();
        $service->getRows($table, $post($table, ['p_name' => 'item 1', 'p_status' => '==new']));
    },
    'refresh_legacy_sorted_page_100' => static function () use ($service, $productTable, $post) {
        $table = $productTable();
        $service->getRows($table, $post($table, ['sortColumn' => 'p_name', 'sortReverse' => '1'], ['page' => '100']));
    },
    'refresh_initial_order_by' => static function () use ($service, $productTable, $post) {
        $table = $productTable();
        $table->getQueryBuilder()->orderBy('p.name');
        $service->getRows($table, $post($table));
    },
    'refresh_repository' => static function () use ($service, $productTable, $post) {
        $table = $productTable()->setEntityLoaderRepository(Product::class);
        $service->getRows($table, $post($table));
    },
    'refresh_callback' => static function () use ($service, $productTable, $post, $em) {
        $table = $productTable()->setEntityLoaderCallback(static fn (array $ids) => $em->getRepository(Product::class)->findBy(['id' => $ids]));
        $service->getRows($table, $post($table));
    },
    'refresh_repository_1000_rows' => static function () use ($service, $productTable, $post) {
        $table = $productTable()->setEntityLoaderRepository(Product::class);
        $service->getRows($table, $post($table, [], ['rowsPerPage' => '1000']));
    },
    'refresh_callback_1000_rows' => static function () use ($service, $productTable, $post, $em) {
        $table = $productTable()->setEntityLoaderCallback(static fn (array $ids) => $em->getRepository(Product::class)->findBy(['id' => $ids]));
        $service->getRows($table, $post($table, [], ['rowsPerPage' => '1000']));
    },
    'refresh_identifier_count' => static function () use ($service, $productTable, $post) {
        $table = $productTable()->setDefaultIdentifierFieldNames();
        $service->getRows($table, $post($table, ['p_name' => 'item 1']));
    },
    'refresh_to_many_join' => static function () use ($service, $em, $post) {
        $queryBuilder = $em->createQueryBuilder()->select('c')->from(Category::class, 'c')->leftJoin('c.products', 'p');
        $table = (new Table())
            ->setId('categories')
            ->setPath('/categories')
            ->setQueryBuilder($queryBuilder, 'c')
            ->addColumn((new Column())->setName('c_name')->setFilter((new Filter())->setName('p_name')->setField('p.name')));
        $service->getRows($table, $post($table));
    },
    'refresh_group_by' => static function () use ($service, $categoryTable, $post) {
        $table = $categoryTable(false);
        $service->getRows($table, $post($table));
    },
    'refresh_group_by_with_totals' => static function () use ($service, $categoryTable, $post) {
        $table = $categoryTable();
        $service->getRows($table, $post($table));
    },
    'handle_request' => static function () use ($service, $productTable, $post) {
        $table = $productTable();
        $service->handleRequest($table, $post($table, ['p_name' => 'item']));
    },
    'export_csv_all_rows' => static function () use ($service, $productTable) {
        $table = $productTable();
        $service->exportAsCsv($table, new Request());
    },
];

$results = [];
foreach ($scenarios as $name => $scenario) {
    $times = [];
    $memory = 0;
    $queries = 0;
    for ($run = 0; $run <= $runs; ++$run) {
        $em->clear();
        gc_collect_cycles();
        $logger->reset();
        memory_reset_peak_usage();
        $baseMemory = memory_get_usage();
        $start = hrtime(true);

        $scenario();

        $elapsed = (hrtime(true) - $start) / 1e6;
        if (0 === $run) {
            // warm-up: metadata, query and template caches
            continue;
        }
        $times[] = $elapsed;
        $memory = max($memory, memory_get_peak_usage() - $baseMemory);
        $queries = $logger->count();
    }
    sort($times);
    $results[$name] = [
        'queries' => $queries,
        'time_ms' => round($times[intdiv(count($times), 2)], 2),
        'memory_kb' => round($memory / 1024),
    ];
}

$platform = $em->getConnection()->getParams()['driver'] ?? '';
printf("%d products, %d runs, PHP %s, %s, ORM %s\n\n", $rowsCount, $runs, \PHP_VERSION, $platform, Composer\InstalledVersions::getPrettyVersion('doctrine/orm'));
printf("%-32s %8s %12s %12s\n", 'scenario', 'queries', 'time (ms)', 'memory (KB)');
foreach ($results as $name => $result) {
    printf("%-32s %8d %12.2f %12d\n", $name, $result['queries'], $result['time_ms'], $result['memory_kb']);
}
printf("\n%d deprecation notices\n", $deprecations);

if (isset($options['json'])) {
    file_put_contents($options['json'], json_encode(['rows' => $rowsCount, 'runs' => $runs, 'php' => \PHP_VERSION, 'platform' => $platform, 'results' => $results], \JSON_PRETTY_PRINT)."\n");
}

function generateData(EntityManager $em, int $rowsCount): void
{
    $statuses = ['new', 'validated', 'cancelled'];
    $connection = $em->getConnection();
    $connection->beginTransaction();
    foreach (['fruits', 'games', 'tools', 'empty'] as $name) {
        $connection->insert('kilik_category', ['name' => $name]);
    }
    for ($i = 1; $i <= $rowsCount; ++$i) {
        $connection->insert('kilik_product', [
            'name' => sprintf('item %05d', ($i * 7919) % $rowsCount),
            'description' => 0 === $i % 5 ? null : 'description '.$i,
            'status' => $statuses[$i % 3],
            'price' => ($i * 37) % 100000,
            'quantity' => $i % 50,
            'createdAt' => sprintf('2024-%02d-%02d 12:00:00', 1 + $i % 12, 1 + $i % 28),
            'category_id' => 1 + $i % 3,
        ]);
    }
    $connection->commit();
}
