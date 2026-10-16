<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Services;

use Kilik\TableBundle\Services\LooseIdentifierIndex;
use PHPUnit\Framework\TestCase;

/**
 * The index must find the same entity as comparing the identifier to each entity identifier with ==.
 */
final class LooseIdentifierIndexTest extends TestCase
{
    private const IDENTIFIERS = [
        0, 1, 10, -1, \PHP_INT_MAX, '0', '-0', '0.0', '1', '01', '1.0', ' 1', '1 ', "1\n", '+1', '1e0', '1E0', '1e1', '10',
        '0x1', '0b1', '1_000', '1a', 'a', 'A', '', '9223372036854775807', '9223372036854775808', '1e309', 0.0, -0.0, 1.0,
        1.5, \INF, null, true, false,
    ];

    /**
     * @dataProvider identifierProvider
     */
    public function testFindsTheFirstLooselyEqualEntity($identifier): void
    {
        $entities = array_map(static fn ($entityIdentifier) => new class($entityIdentifier) {
            public function __construct(private $identifier)
            {
            }

            public function getId()
            {
                return $this->identifier;
            }
        }, self::IDENTIFIERS);

        foreach ([$entities, array_reverse($entities)] as $orderedEntities) {
            $this->assertSame(self::findFirstEqual($identifier, $orderedEntities), (new LooseIdentifierIndex($orderedEntities))->find($identifier));
        }
    }

    public function identifierProvider(): iterable
    {
        foreach (self::IDENTIFIERS as $identifier) {
            yield var_export($identifier, true) => [$identifier];
        }
    }

    public function testWithoutEntity(): void
    {
        $this->assertNull((new LooseIdentifierIndex([]))->find(1));
    }

    /**
     * @param list<object> $entities
     */
    private static function findFirstEqual($identifier, array $entities): ?object
    {
        foreach ($entities as $entity) {
            if ($identifier == $entity->getId()) {
                return $entity;
            }
        }

        return null;
    }
}
