<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\App;

use Kilik\TableBundle\Services\TableApiService;
use Kilik\TableBundle\Services\TableService;

/**
 * Application service relying on autowiring, as applications do.
 */
final class AutowiredServices
{
    public TableService $tableService;

    public TableApiService $tableApiService;

    public function __construct(TableService $tableService, TableApiService $tableApiService)
    {
        $this->tableService = $tableService;
        $this->tableApiService = $tableApiService;
    }
}
