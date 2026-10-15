<?php

namespace Kilik\TableBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Kilik\TableBundle\Tests\Fixtures\Entity\Category;
use Kilik\TableBundle\Tests\Fixtures\Entity\Document;
use Kilik\TableBundle\Tests\Fixtures\Entity\Product;
use Symfony\Component\Uid\Uuid;

/**
 * Deterministic data set shared by the integration tests.
 *
 * Identifiers are assigned in insertion order (products 1 to 25, categories 1 to 4).
 * Text values are lowercase ASCII so that LIKE and ORDER BY behave the same on every platform.
 */
final class Fixtures
{
    public const CATEGORIES = ['fruits', 'games', 'tools', 'empty'];

    /**
     * id => [name, description, status, price, quantity, createdAt, category name].
     */
    public const PRODUCTS = [
        1 => ['red apple', 'fresh and sweet', 'new', 120, 10, '2024-01-15 10:30:00', 'fruits'],
        2 => ['green apple', null, 'validated', 80, 5, '2024-02-01 08:00:00', 'fruits'],
        3 => ['apple pie', 'homemade', 'new', 450, 12, '2024-03-10 12:00:00', 'fruits'],
        4 => ['banana', 'yellow fruit', 'cancelled', 60, 0, '2024-02-29 23:59:59', 'fruits'],
        5 => ['chess board', 'wooden board', 'validated', 3000, 7, '2024-01-01 00:00:00', 'games'],
        6 => ['puzzle', null, 'cancelled', 2000, 0, '2024-05-20 14:00:00', 'games'],
        7 => ['yellow submarine', 'toy', 'validated', 4500, 3, '2023-12-31 23:59:59', 'games'],
        8 => ['card game', 'red and black cards', 'new', 950, 30, '2024-06-01 09:00:00', 'games'],
        9 => ['hammer', 'steel head', 'new', 1500, 25, '2024-04-05 09:15:00', 'tools'],
        10 => ['screwdriver', null, 'new', 800, 40, '2024-04-05 18:45:30', 'tools'],
        11 => ['red toolbox', 'red plastic box', 'validated', 3500, 2, null, 'tools'],
        12 => ['wrench', 'adjustable', 'cancelled', 1200, 0, '2024-07-14 07:14:00', 'tools'],
        13 => ['blue house', 'model kit', 'new', 250000, 1, '2024-08-08 08:08:08', null],
        14 => ['red house', 'model kit', 'validated', 300000, 1, null, null],
        15 => ['discount 50%', 'percent sign in name', 'new', 50, 100, '2024-09-01 00:00:00', null],
        16 => ['green tea', 'organic', 'validated', 300, 50, '2024-10-10 10:10:10', 'fruits'],
        17 => ['black tea', null, 'new', 280, 45, '2024-10-11 11:11:11', 'fruits'],
        18 => ['dice', 'six faces', 'new', 150, 60, '2024-11-30 23:00:00', 'games'],
        19 => ['saw', 'sharp blade', 'cancelled', 2200, 4, '2024-12-01 00:00:01', 'tools'],
        20 => ['drill', 'cordless drill', 'validated', 8900, 6, '2024-12-24 18:30:00', 'tools'],
        21 => ['orange', 'juicy', 'new', 70, 80, '2025-01-01 00:00:00', 'fruits'],
        22 => ['kite', null, 'validated', 1800, 9, '2025-02-14 14:14:14', 'games'],
        23 => ['nails', 'box of 100', 'new', 400, 500, '2025-03-03 03:03:03', 'tools'],
        24 => ['pear', 'green fruit', 'validated', 90, 15, '2025-04-04 04:04:04', 'fruits'],
        25 => ['yo-yo', 'wooden toy', 'new', 350, 20, null, 'games'],
    ];

    public const DOCUMENTS = [
        '0190b0a4-4a7e-7c3e-8a51-1f0c1d3e5a01' => 'alpha',
        '0190b0a4-4a7e-7c3e-8a51-1f0c1d3e5a02' => 'beta',
        '0190b0a4-4a7e-7c3e-8a51-1f0c1d3e5a03' => 'gamma',
    ];

    public static function load(EntityManagerInterface $em): void
    {
        $categories = [];
        foreach (self::CATEGORIES as $name) {
            $categories[$name] = new Category($name);
            $em->persist($categories[$name]);
        }
        // categories first, so that their identifiers follow the declaration order
        $em->flush();

        foreach (self::PRODUCTS as [$name, $description, $status, $price, $quantity, $createdAt, $category]) {
            $em->persist(new Product(
                $name,
                $description,
                $status,
                $price,
                $quantity,
                null === $createdAt ? null : new \DateTimeImmutable($createdAt),
                null === $category ? null : $categories[$category],
            ));
            // one flush per product keeps identifiers in declaration order on every platform
            $em->flush();
        }

        foreach (self::DOCUMENTS as $uuid => $title) {
            $em->persist(new Document(Uuid::fromString($uuid), $title));
        }
        $em->flush();
        $em->clear();
    }
}
