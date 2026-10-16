<?php

namespace Kilik\TableBundle\Services;

/**
 * Finds the first entity whose identifier is loosely equal (==) to a given identifier.
 *
 * Integer and string identifiers are indexed by a key shared by all the values loosely equal to them; the other
 * identifiers (floats, objects, booleans, null...) are compared to every given identifier, as before indexing.
 *
 * @internal
 */
final class LooseIdentifierIndex
{
    /**
     * @var list<object>
     */
    private $entities = [];

    /**
     * Identifier of each entity.
     *
     * @var list<mixed>
     */
    private $identifiers = [];

    /**
     * Positions of the entities by identifier key.
     *
     * @var array<string, list<int>>
     */
    private $positionsByKey = [];

    /**
     * Positions of the entities whose identifier has no key.
     *
     * @var list<int>
     */
    private $unkeyedPositions = [];

    /**
     * @param iterable<object> $entities
     */
    public function __construct(iterable $entities)
    {
        foreach ($entities as $entity) {
            $position = \count($this->entities);
            $this->entities[] = $entity;
            $this->identifiers[] = $identifier = $entity->getId();

            $key = self::getKey($identifier);
            if (null === $key) {
                $this->unkeyedPositions[] = $position;
            } else {
                $this->positionsByKey[$key][] = $position;
            }
        }
    }

    public function find($identifier): ?object
    {
        $key = self::getKey($identifier);
        if (null === $key) {
            $positions = array_keys($this->entities);
        } elseif ([] === $this->unkeyedPositions) {
            $positions = $this->positionsByKey[$key] ?? [];
        } else {
            $positions = array_merge($this->positionsByKey[$key] ?? [], $this->unkeyedPositions);
            sort($positions);
        }

        foreach ($positions as $position) {
            if ($identifier == $this->identifiers[$position]) {
                return $this->entities[$position];
            }
        }

        return null;
    }

    /**
     * A key shared by all the integers and strings loosely equal to the value, or null for other types.
     *
     * Numeric values are compared as numbers, other strings as strings.
     */
    private static function getKey($value): ?string
    {
        if (\is_int($value) || (\is_string($value) && is_numeric($value))) {
            // adding 0.0 turns -0.0 into 0.0
            return 'n'.((float) $value + 0.0);
        }

        return \is_string($value) ? 's'.$value : null;
    }
}
