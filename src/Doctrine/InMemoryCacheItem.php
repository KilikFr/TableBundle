<?php

namespace Kilik\TableBundle\Doctrine;

use Psr\Cache\CacheItemInterface;

/**
 * Item of {@see InMemoryResultCache}.
 *
 * Parameters are not typed, to be compatible with every version of psr/cache.
 *
 * @internal
 */
final class InMemoryCacheItem implements CacheItemInterface
{
    /**
     * @var string
     */
    private $key;

    private $value;

    /**
     * @var bool
     */
    private $hit;

    public function __construct(string $key, $value = null, bool $hit = false)
    {
        $this->key = $key;
        $this->value = $value;
        $this->hit = $hit;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set($value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @param \DateTimeInterface|null $expiration
     */
    public function expiresAt($expiration): static
    {
        return $this;
    }

    /**
     * @param int|\DateInterval|null $time
     */
    public function expiresAfter($time): static
    {
        return $this;
    }
}
