<?php

namespace Kilik\TableBundle\Tests\Doctrine;

use Psr\Log\AbstractLogger;

/**
 * Collects the SQL statements logged by the DBAL logging middleware.
 */
final class QueryLogger extends AbstractLogger
{
    /**
     * @var list<string>
     */
    private array $queries = [];

    public function log($level, $message, array $context = []): void
    {
        if (isset($context['sql'])) {
            $this->queries[] = $context['sql'];
        }
    }

    /**
     * @return list<string>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function count(): int
    {
        return \count($this->queries);
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}
