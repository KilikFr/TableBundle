<?php

namespace Kilik\TableBundle\Doctrine;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Result cache living as long as the object, used to hydrate the rows of a query twice while executing it once.
 *
 * Items never expire. Parameters are not typed, to be compatible with every version of psr/cache.
 *
 * @internal
 */
final class InMemoryResultCache implements CacheItemPoolInterface
{
    /**
     * @var array<string, mixed>
     */
    private $values = [];

    /**
     * @param string $key
     */
    public function getItem($key): CacheItemInterface
    {
        if (\array_key_exists($key, $this->values)) {
            return new InMemoryCacheItem($key, $this->values[$key], true);
        }

        return new InMemoryCacheItem($key);
    }

    /**
     * @param string[] $keys
     *
     * @return array<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * @param string $key
     */
    public function hasItem($key): bool
    {
        return \array_key_exists($key, $this->values);
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    /**
     * @param string $key
     */
    public function deleteItem($key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    /**
     * @param string[] $keys
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->values[$item->getKey()] = $item->get();

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}
