<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests;

/**
 * Compares a value with a reference file stored in tests/__snapshots__.
 *
 * Missing snapshots make the test fail; run the suite with KILIK_UPDATE_SNAPSHOTS=1 to (re)write them,
 * then review the diff before committing.
 */
trait SnapshotTrait
{
    protected function assertMatchesSnapshot(string $actual, string $name): void
    {
        $class = substr(static::class, strrpos(static::class, '\\') + 1);
        $path = __DIR__.'/__snapshots__/'.$class.'/'.$name;

        if ('1' === getenv('KILIK_UPDATE_SNAPSHOTS')) {
            if (!is_dir(\dirname($path))) {
                mkdir(\dirname($path), 0777, true);
            }
            file_put_contents($path, $actual);
        }

        if (!is_file($path)) {
            $this->fail(sprintf('Snapshot "%s" is missing, run the tests with KILIK_UPDATE_SNAPSHOTS=1 to create it.', $path));
        }

        $this->assertSame(file_get_contents($path), $actual, sprintf('Output differs from snapshot "%s".', $path));
    }
}
