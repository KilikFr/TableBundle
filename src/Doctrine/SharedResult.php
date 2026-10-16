<?php

namespace Kilik\TableBundle\Doctrine;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\Proxy;

/**
 * Executes a query once to hydrate its rows as objects, then as scalars.
 *
 * The rows fetched by the first hydration are kept in an in-memory result cache. Both hydrations must execute the
 * same SQL: the foreign keys, only selected for objects, are selected for scalars too, then removed from the rows.
 *
 * @internal
 */
final class SharedResult
{
    /**
     * @var InMemoryResultCache
     */
    private $resultCache;

    /**
     * Keys of the foreign keys added to the scalar rows.
     *
     * @var list<string>
     */
    private $addedKeys;

    /**
     * @param list<string> $addedKeys
     */
    private function __construct(InMemoryResultCache $resultCache, array $addedKeys)
    {
        $this->resultCache = $resultCache;
        $this->addedKeys = $addedKeys;
    }

    /**
     * Prepares the query to share its result, or returns null when it cannot be shared safely.
     */
    public static function share(Query $query): ?self
    {
        $addedKeys = self::getAddedKeys($query);
        if (null === $addedKeys || !self::hasSerializableParameters($query)) {
            return null;
        }

        $resultCache = new InMemoryResultCache();
        $query->setHint(Query::HINT_INCLUDE_META_COLUMNS, true);
        $query->setResultCache($resultCache);

        return new self($resultCache, $addedKeys);
    }

    /**
     * Removes the added foreign keys from the scalar rows, and frees the cached rows.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    public function cleanScalarRows(array $rows): array
    {
        $this->resultCache->clear();

        if ([] !== $this->addedKeys) {
            foreach ($rows as $index => $row) {
                foreach ($this->addedKeys as $key) {
                    unset($rows[$index][$key]);
                }
            }
        }

        return $rows;
    }

    /**
     * Keys added to the scalar rows by selecting the foreign keys, or null if they would replace other values.
     *
     * @return list<string>|null
     */
    private static function getAddedKeys(Query $query): ?array
    {
        // partial objects are hydrated without foreign keys
        if ($query->getHint(Query::HINT_FORCE_PARTIAL_LOAD)) {
            return null;
        }
        if ($query->getHint(Query::HINT_INCLUDE_META_COLUMNS)) {
            return [];
        }

        $scalarMapping = self::parseResultSetMapping($query, false);
        $sharedMapping = self::parseResultSetMapping($query, true);

        $scalarKeys = array_count_values(self::getKeys($scalarMapping));
        $sharedKeys = array_count_values(self::getKeys($sharedMapping));
        $scalarMetaKeys = [];
        foreach ($scalarMapping->metaMappings as $column => $name) {
            $scalarMetaKeys[$scalarMapping->columnOwnerMap[$column].'_'.$name] = true;
        }

        $addedKeys = [];
        foreach ($sharedMapping->metaMappings as $column => $name) {
            $key = $sharedMapping->columnOwnerMap[$column].'_'.$name;
            if (isset($scalarMetaKeys[$key])) {
                continue;
            }
            if (isset($scalarKeys[$key]) || $sharedKeys[$key] > 1) {
                return null;
            }
            $addedKeys[] = $key;
        }

        return $addedKeys;
    }

    private static function parseResultSetMapping(Query $query, bool $includeMetaColumns): ResultSetMapping
    {
        $parsedQuery = clone $query;
        foreach ($query->getHints() as $name => $value) {
            $parsedQuery->setHint($name, $value);
        }
        $parsedQuery->setHint(Query::HINT_INCLUDE_META_COLUMNS, $includeMetaColumns);
        $parsedQuery->setHydrationMode(Query::HYDRATE_SCALAR);

        return (new Parser($parsedQuery))->parse()->getResultSetMapping();
    }

    /**
     * Keys of the scalar rows, as built by the hydrators.
     *
     * @return list<string>
     */
    private static function getKeys(ResultSetMapping $mapping): array
    {
        $keys = [];
        foreach ($mapping->fieldMappings as $column => $name) {
            $keys[] = $mapping->columnOwnerMap[$column].'_'.$name;
        }
        foreach ($mapping->metaMappings as $column => $name) {
            $keys[] = $mapping->columnOwnerMap[$column].'_'.$name;
        }
        foreach ($mapping->scalarMappings as $name) {
            $keys[] = (string) $name;
        }

        return $keys;
    }

    /**
     * Whether the query parameters can be serialized, as needed to compute the result cache key.
     *
     * Entities are replaced by their identifiers by the ORM. Other objects are checked by serializing them.
     */
    private static function hasSerializableParameters(Query $query): bool
    {
        $metadataFactory = $query->getEntityManager()->getMetadataFactory();
        $values = [];
        foreach ($query->getParameters() as $parameter) {
            $values[] = $parameter->getValue();
        }

        while ([] !== $values) {
            $value = array_pop($values);
            if (\is_array($value)) {
                array_push($values, ...array_values($value));
            } elseif (!\is_object($value) || $value instanceof \DateTimeInterface || $value instanceof \UnitEnum) {
                continue;
            } elseif ($value instanceof Proxy || $metadataFactory->hasMetadataFor($value::class)) {
                array_push($values, ...array_values($metadataFactory->getMetadataFor($value::class)->getIdentifierValues($value)));
            } else {
                try {
                    serialize($value);
                } catch (\Throwable $exception) {
                    return false;
                }
            }
        }

        return true;
    }
}
