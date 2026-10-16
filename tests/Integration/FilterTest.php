<?php

declare(strict_types=1);

namespace Kilik\TableBundle\Tests\Integration;

use Doctrine\ORM\QueryBuilder;
use Kilik\TableBundle\Components\Column;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\FilterCheckbox;
use Kilik\TableBundle\Components\FilterDate;
use Kilik\TableBundle\Components\FilterSelect;
use Kilik\TableBundle\Components\Table;

/**
 * Freezes how filters are turned into query conditions by TableService.
 *
 * Fixture product names, for reference:
 *  1 red apple, 2 green apple, 3 apple pie, 4 banana, 5 chess board, 6 puzzle, 7 yellow submarine, 8 card game,
 *  9 hammer, 10 screwdriver, 11 red toolbox, 12 wrench, 13 blue house, 14 red house, 15 discount 50%, 16 green tea,
 *  17 black tea, 18 dice, 19 saw, 20 drill, 21 orange, 22 kite, 23 nails, 24 pear, 25 yo-yo
 */
final class FilterTest extends IntegrationTestCase
{
    /**
     * @dataProvider autoTypeOnNameProvider
     *
     * @param list<int> $expectedIds
     */
    public function testAutoTypeOnText(string $input, array $expectedIds): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'));

        $this->assertFilteredIds($expectedIds, $table, ['p_name' => $input]);
    }

    public function autoTypeOnNameProvider(): iterable
    {
        yield 'contains' => ['apple', [1, 2, 3]];
        yield 'trimmed' => ['  apple  ', [1, 2, 3]];
        yield 'empty value is ignored' => ['', range(1, 25)];
        yield 'blank value is ignored' => ['   ', range(1, 25)];
        yield 'zero is a value' => ['0', [15]];
        yield '= is a LIKE without wildcards' => ['=green apple', [2]];
        yield '= does not match a part' => ['=apple', []];
        yield '== is a strict equality' => ['==green apple', [2]];
        yield '! is NOT LIKE' => ['!apple', array_values(array_diff(range(1, 25), [1, 2, 3]))];
        yield '!= is a difference' => ['!=green apple', array_values(array_diff(range(1, 25), [2]))];
        yield 'operator without value is ignored' => ['>', range(1, 25)];
        yield '= without value is ignored' => ['=', range(1, 25)];
        yield 'percent is not escaped' => ['50%', [15]];
        yield 'underscore is a wildcard' => ['_', range(1, 25)];
    }

    /**
     * @dataProvider comparisonOnPriceProvider
     *
     * @param list<int> $expectedIds
     */
    public function testAutoTypeComparisonOperators(string $input, array $expectedIds): void
    {
        $table = $this->productTable($this->filteredColumn('p.price'));

        $this->assertFilteredIds($expectedIds, $table, ['p_price' => $input]);
    }

    public function comparisonOnPriceProvider(): iterable
    {
        yield '>' => ['>1000', [5, 6, 7, 9, 11, 12, 13, 14, 19, 20, 22]];
        yield '>=' => ['>=1500', [5, 6, 7, 9, 11, 13, 14, 19, 20, 22]];
        yield '<' => ['<100', [2, 4, 15, 21, 24]];
        yield '<=' => ['<=80', [2, 4, 15, 21]];
        yield '==' => ['==450', [3]];
        yield '!=' => ['!=450', array_values(array_diff(range(1, 25), [3]))];
    }

    /**
     * A text filter on a numeric column produces "integer LIKE string", rejected by PostgreSQL.
     */
    public function testAutoTypeLikeOnNumericColumn(): void
    {
        $table = $this->productTable($this->filteredColumn('p.price'));

        if ('postgresql' === self::platform()) {
            $this->expectException(\Doctrine\DBAL\Exception::class);
        }

        $this->assertFilteredIds([5, 6, 7, 9, 10, 11, 12, 13, 14, 16, 19, 20, 22, 23], $table, ['p_price' => '00']);
    }

    /**
     * LIKE case sensitivity depends on the database.
     */
    public function testCaseSensitivityDependsOnThePlatform(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'));
        $expected = 'postgresql' === self::platform() ? [] : [1, 2, 3];

        $this->assertFilteredIds($expected, $table, ['p_name' => 'APPLE']);
    }

    /**
     * @dataProvider explicitTypeProvider
     *
     * @param list<int> $expectedIds
     */
    public function testExplicitType(string $type, string $field, string $input, array $expectedIds): void
    {
        $table = $this->productTable($this->filteredColumn($field, (new Filter())->setType($type)));

        $this->assertFilteredIds($expectedIds, $table, [str_replace('.', '_', $field) => $input]);
    }

    public function explicitTypeProvider(): iterable
    {
        yield 'like keeps operators as text' => [Filter::TYPE_LIKE, 'p.name', '=green apple', []];
        yield 'like' => [Filter::TYPE_LIKE, 'p.name', 'house', [13, 14]];
        yield 'equal' => [Filter::TYPE_EQUAL, 'p.name', 'green apple', [2]];
        yield 'equal strict' => [Filter::TYPE_EQUAL_STRICT, 'p.name', 'green apple', [2]];
        yield 'not equal' => [Filter::TYPE_NOT_EQUAL, 'p.name', 'green apple', array_values(array_diff(range(1, 25), [2]))];
        yield 'not like' => [Filter::TYPE_NOT_LIKE, 'p.name', 'apple', array_values(array_diff(range(1, 25), [1, 2, 3]))];
        yield 'greater' => [Filter::TYPE_GREATER, 'p.price', '4500', [13, 14, 20]];
        yield 'greater or equal' => [Filter::TYPE_GREATER_OR_EQUAL, 'p.price', '4500', [7, 13, 14, 20]];
        yield 'less' => [Filter::TYPE_LESS, 'p.price', '70', [4, 15]];
        yield 'less or equal' => [Filter::TYPE_LESS_OR_EQUAL, 'p.price', '70', [4, 15, 21]];
        yield 'words and' => [Filter::TYPE_LIKE_WORDS_AND, 'p.name', 'house red', [14]];
        yield 'words and, extra spaces' => [Filter::TYPE_LIKE_WORDS_AND, 'p.name', ' apple   red ', [1]];
        yield 'words or' => [Filter::TYPE_LIKE_WORDS_OR, 'p.name', 'pie tea', [3, 16, 17]];
        yield 'auto' => [Filter::TYPE_AUTO, 'p.name', '==dice', [18]];
    }

    /**
     * "0" is removed from the word list, like spaces: without any word left, the filter is ignored.
     *
     * Before 3.5.0, an undefined variable was read, which raised a PHP warning.
     */
    public function testWordsFilterWithZeroOnlyIsIgnored(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name', (new Filter())->setType(Filter::TYPE_LIKE_WORDS_AND)));

        $this->assertFilteredIds(range(1, 25), $table, ['p_name' => '0']);
    }

    /**
     * Before 3.5.0, the condition of the previous filter was added again instead.
     */
    public function testWordsFilterWithZeroOnlyAfterAnotherFilterIsIgnored(): void
    {
        $table = $this->productTable(
            $this->filteredColumn('p.status'),
            $this->filteredColumn('p.name', (new Filter())->setType(Filter::TYPE_LIKE_WORDS_AND))
        );

        $validatedIds = self::fixtureIds(static fn (array $product): bool => 'validated' === $product[2]);
        $this->assertFilteredIds($validatedIds, $table, ['p_status' => '==validated', 'p_name' => '0']);
    }

    public function testInvalidTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Filter())->setType('unknown');
    }

    /**
     * @dataProvider integerFormatProvider
     *
     * @param list<int> $expectedIds
     */
    public function testIntegerFormat(string $input, array $expectedIds): void
    {
        $filter = (new Filter())->setDataFormat(Filter::FORMAT_INTEGER);
        $table = $this->productTable($this->filteredColumn('p.quantity', $filter));

        $this->assertFilteredIds($expectedIds, $table, ['p_quantity' => $input]);
    }

    public function integerFormatProvider(): iterable
    {
        yield 'plain value is a strict equality' => ['0', [4, 6, 12]];
        yield 'not a number becomes 0' => ['abc', [4, 6, 12]];
        yield '! becomes !=' => ['!0', array_values(array_diff(range(1, 25), [4, 6, 12]))];
        yield '>' => ['>100', [23]];
        yield '<=' => ['<=1', [4, 6, 12, 13, 14]];
    }

    /**
     * With the integer format, "=" is kept as a LIKE, rejected by PostgreSQL on an integer column.
     */
    public function testIntegerFormatWithEqualOperator(): void
    {
        $filter = (new Filter())->setDataFormat(Filter::FORMAT_INTEGER);
        $table = $this->productTable($this->filteredColumn('p.quantity', $filter));

        if ('postgresql' === self::platform()) {
            $this->expectException(\Doctrine\DBAL\Exception::class);
        }

        $this->assertFilteredIds([2], $table, ['p_quantity' => '=5']);
    }

    /**
     * Legacy date format (deprecated in favor of FilterDate): day/month/year input converted to Y-m-d.
     */
    public function testDeprecatedDateFormatWithLike(): void
    {
        $filter = (new Filter())->setDataFormat(Filter::FORMAT_DATE);
        $table = $this->productTable($this->filteredColumn('p.createdAt', $filter));

        if ('postgresql' === self::platform()) {
            // timestamp LIKE text
            $this->expectException(\Doctrine\DBAL\Exception::class);
        }

        $this->assertFilteredIds([9, 10], $table, ['p_createdAt' => '05/04/2024']);
    }

    public function testDeprecatedDateFormatWithComparison(): void
    {
        $filter = (new Filter())->setDataFormat(Filter::FORMAT_DATE);
        $table = $this->productTable($this->filteredColumn('p.createdAt', $filter));

        $this->assertFilteredIds([21, 22, 23, 24], $table, ['p_createdAt' => '>=01/01/2025']);
    }

    /**
     * @dataProvider specialTypeProvider
     *
     * @param list<int> $expectedIds
     */
    public function testSpecialTypesFromInputFormatter(string $field, string $operator, $value, array $expectedIds): void
    {
        $filter = (new Filter())->setInputFormatter(static fn (Filter $filter, $defaultOperator, $input) => [$operator, $value ?? $input]);
        $table = $this->productTable($this->filteredColumn($field, $filter));

        $this->assertFilteredIds($expectedIds, $table, [str_replace('.', '_', $field) => 'any']);
    }

    public function specialTypeProvider(): iterable
    {
        $new = [1, 3, 8, 9, 10, 13, 15, 17, 18, 21, 23, 25];
        $cancelled = [4, 6, 12, 19];
        $validated = [2, 5, 7, 11, 14, 16, 20, 22, 24];
        $merge = static function (array ...$lists): array {
            $ids = array_merge(...$lists);
            sort($ids);

            return $ids;
        };

        yield 'null' => ['p.description', Filter::TYPE_NULL, null, [2, 6, 10, 17, 22]];
        yield 'not null' => ['p.description', Filter::TYPE_NOT_NULL, null, array_values(array_diff(range(1, 25), [2, 6, 10, 17, 22]))];
        yield 'in from a comma separated string' => ['p.status', Filter::TYPE_IN, 'new,cancelled', $merge($new, $cancelled)];
        yield 'in from an array' => ['p.status', Filter::TYPE_IN, ['new', 'validated'], $merge($new, $validated)];
        yield 'not in' => ['p.status', Filter::TYPE_NOT_IN, 'new,cancelled', $validated];
    }

    public function testInputFormatterReceivesOperatorAndValue(): void
    {
        $calls = [];
        $filter = (new Filter())->setInputFormatter(static function (Filter $filter, $operator, $input) use (&$calls) {
            $calls[] = [$filter->getName(), $operator, $input];

            return [Filter::TYPE_EQUAL_STRICT, strtolower($input)];
        });
        $table = $this->productTable($this->filteredColumn('p.name', $filter));

        $this->assertFilteredIds([18], $table, ['p_name' => ' >=DICE ']);
        // called for the main query and the filtered count
        $this->assertSame([['p_name', '>=', 'DICE'], ['p_name', '>=', 'DICE']], $calls);
    }

    public function testFilterSelectIsAStrictEquality(): void
    {
        $filter = (new FilterSelect())->setChoices(['new' => 'new', 'validated' => 'validated', 'cancelled' => 'cancelled']);
        $table = $this->productTable($this->filteredColumn('p.status', $filter));

        $this->assertSame(Filter::TYPE_EQUAL_STRICT, $filter->getType());
        $this->assertFilteredIds([4, 6, 12, 19], $table, ['p_status' => 'cancelled']);

        $table = $this->productTable($this->filteredColumn('p.status', $filter));
        $this->assertFilteredIds(range(1, 25), $table, ['p_status' => '']);
    }

    public function testFilterCheckboxWithQueryPartBuilder(): void
    {
        $values = [];
        $filter = (new FilterCheckbox())->setQueryPartBuilder(
            static function (Filter $filter, Table $table, QueryBuilder $queryBuilder, $value) use (&$values) {
                $values[] = $value;
                if ('1' === $value) {
                    $queryBuilder->andWhere('p.quantity = 0');
                }
            }
        );
        $table = $this->productTable($this->filteredColumn('p.quantity', $filter));

        $this->assertFilteredIds([4, 6, 12], $table, ['p_quantity' => '1']);
        $this->assertSame(['1', '1'], $values);

        $values = [];
        $table = $this->productTable($this->filteredColumn('p.quantity', $filter));
        $this->assertFilteredIds(range(1, 25), $table, []);
        $this->assertSame([], $values);
    }

    /**
     * @dataProvider filterDateProvider
     *
     * @param list<int> $expectedIds
     */
    public function testFilterDate(string $inputFormat, string $input, array $expectedIds): void
    {
        $filter = (new FilterDate())->setInputFormat($inputFormat);
        $table = $this->productTable($this->filteredColumn('p.createdAt', $filter));

        $this->assertFilteredIds($expectedIds, $table, ['p_createdAt' => $input]);
    }

    public function filterDateProvider(): iterable
    {
        $big = FilterDate::INPUT_FORMAT_BIG_ENDIAN;
        $little = FilterDate::INPUT_FORMAT_LITTLE_ENDIAN;

        yield 'day' => [$big, '2024-04-05', [9, 10]];
        yield 'day, little endian' => [$little, '05/04/2024', [9, 10]];
        yield 'day and hour' => [$big, '2024-04-05 18', [10]];
        yield 'month' => [$big, '2024-02', [2, 4]];
        yield 'year and after' => [$big, '>=2025', [21, 22, 23, 24]];
        yield 'before a day' => [$big, '<2024-01-15', [5, 7]];
        yield 'after a day' => [$big, '>2024-12-01', [20, 21, 22, 23, 24]];
        yield 'not on a day' => [$big, '!=2024-04-05', array_values(array_diff(range(1, 25), [9, 10, 11, 14, 25]))];
        yield 'empty date' => [$big, '=', [11, 14, 25]];
        yield 'not empty date' => [$big, '!=', array_values(array_diff(range(1, 25), [11, 14, 25]))];
        yield 'invalid date matches nothing' => [$big, 'yesterday', []];
        yield 'empty input is ignored' => [$big, '', range(1, 25)];
    }

    public function testQueryPartBuilderReceivesTheTrimmedValue(): void
    {
        $calls = [];
        $filter = (new Filter())->setQueryPartBuilder(
            static function (Filter $filter, Table $table, QueryBuilder $queryBuilder, $value) use (&$calls) {
                $calls[] = $value;
                if ('' !== $value) {
                    $queryBuilder->andWhere('p.status = :status')->setParameter('status', $value);
                }
            }
        );
        $table = $this->productTable($this->filteredColumn('p.status', $filter));

        $this->assertFilteredIds([4, 6, 12, 19], $table, ['p_status' => ' cancelled ']);
        $this->assertSame(['cancelled', 'cancelled'], $calls);

        // an empty value still reaches the builder
        $calls = [];
        $table = $this->productTable($this->filteredColumn('p.status', $filter));
        $this->assertFilteredIds(range(1, 25), $table, ['p_status' => '']);
        $this->assertSame(['', ''], $calls);
    }

    public function testMultipleValuesAreIgnoredWithoutQueryPartBuilder(): void
    {
        $table = $this->productTable($this->filteredColumn('p.status'));

        $this->assertFilteredIds(range(1, 25), $table, ['p_status' => ['new', 'validated']]);
    }

    public function testMultipleValuesArePassedTrimmedToTheQueryPartBuilder(): void
    {
        $calls = [];
        $filter = (new Filter())->setQueryPartBuilder(
            static function (Filter $filter, Table $table, QueryBuilder $queryBuilder, $values) use (&$calls) {
                $calls[] = $values;
                $queryBuilder->andWhere('p.status IN (:statuses)')->setParameter('statuses', $values);
            }
        );
        $table = $this->productTable($this->filteredColumn('p.status', $filter));

        $this->assertFilteredIds([2, 4, 5, 6, 7, 11, 12, 14, 16, 19, 20, 22, 24], $table, ['p_status' => [' validated', 'cancelled ']]);
        $this->assertSame([['validated', 'cancelled'], ['validated', 'cancelled']], $calls);
    }

    public function testNullValueIsIgnored(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'));

        $this->assertFilteredIds(range(1, 25), $table, ['p_name' => null]);
    }

    public function testUnknownParametersAreIgnored(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'));

        $this->assertFilteredIds(range(1, 25), $table, ['unknown' => 'apple', 'p.name' => 'apple']);
    }

    public function testFiltersAreCombinedWithAnd(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'), $this->filteredColumn('p.status'));

        $this->assertFilteredIds([11, 14], $table, ['p_name' => 'red', 'p_status' => '==validated']);
    }

    public function testTableFilterWithoutColumn(): void
    {
        $createTable = fn () => $this->productTable($this->filteredColumn('p.name'))
            ->addFilter((new Filter())->setName('status')->setField('p.status')->setType(Filter::TYPE_EQUAL_STRICT));

        $this->assertFilteredIds([2, 5, 7, 11, 14, 16, 20, 22, 24], $createTable(), ['status' => 'validated']);
        $this->assertFilteredIds([2], $createTable(), ['status' => 'validated', 'p_name' => 'apple']);
    }

    /**
     * getRows() adds the conditions to the query builder of the table itself. On a second call with the same filter,
     * the condition is added again and its parameter is overwritten; the total count still uses the first value.
     */
    public function testCallingGetRowsTwiceOnTheSameTableReusesItsQueryBuilder(): void
    {
        $table = $this->productTable($this->filteredColumn('p.name'));

        $this->assertFilteredIds([1, 11, 14], $table, ['p_name' => 'red']);

        $rows = $this->getAllRows($table, ['p_name' => 'house']);
        $this->assertSame([13, 14], self::sortedProductIds($rows));
        $this->assertSame(2, $table->getFilteredRows());
        $this->assertSame(3, $table->getTotalRows());
    }

    public function testFilterOnAJoinedEntity(): void
    {
        $table = $this->productTable($this->filteredColumn('c.name'));
        $table->getQueryBuilder()->addSelect('c')->leftJoin('p.category', 'c');

        $this->assertFilteredIds([5, 6, 7, 8, 18, 22, 25], $table, ['c_name' => '==games']);
    }

    public function testHavingFilterOnAnAggregate(): void
    {
        $filter = (new Filter())->setName('nbProducts')->setField('COUNT(p.id)')->setHaving(true)->setDataFormat(Filter::FORMAT_INTEGER);
        $column = (new Column())->setName('nbProducts')->setFilter($filter);
        $table = $this->categoryTable($column);

        $rows = $this->getAllRows($table, ['nbProducts' => '<8']);

        $this->assertSame([2, 3, 4], array_map('intval', self::column($rows, 'c_id')));
        $this->assertSame(['7', '7', '0'], array_map('strval', self::column($rows, 'nbProducts')));
        $this->assertSame(4, $table->getTotalRows());
        $this->assertSame(3, $table->getFilteredRows());
    }

    /**
     * With the default text format, the aggregate is compared to a string: SQLite then considers every number lower.
     */
    public function testHavingFilterOnAnAggregateWithTextFormat(): void
    {
        $filter = (new Filter())->setName('nbProducts')->setField('COUNT(p.id)')->setHaving(true);
        $table = $this->categoryTable((new Column())->setName('nbProducts')->setFilter($filter));

        $rows = $this->getAllRows($table, ['nbProducts' => '<8']);

        $expected = 'sqlite' === self::platform() ? [1, 2, 3, 4] : [2, 3, 4];
        $this->assertSame($expected, array_map('intval', self::column($rows, 'c_id')));
        $this->assertSame(\count($expected), $table->getFilteredRows());
    }

    public function testHavingFilterOnAResultVariable(): void
    {
        $filter = (new Filter())->setName('nbProducts')->setField('nbProducts')->setHaving(true)->setDataFormat(Filter::FORMAT_INTEGER);
        $table = $this->categoryTable((new Column())->setName('nbProducts')->setFilter($filter));

        if ('postgresql' === self::platform()) {
            // PostgreSQL does not accept output column aliases in HAVING
            $this->expectException(\Doctrine\DBAL\Exception::class);
        }

        $rows = $this->getAllRows($table, ['nbProducts' => '>7']);

        $this->assertSame([1], array_map('intval', self::column($rows, 'c_id')));
        $this->assertSame(1, $table->getFilteredRows());
    }

    public function testWhereFilterOnAGroupedQuery(): void
    {
        $table = $this->categoryTable($this->filteredColumn('c.name'));

        $rows = $this->getAllRows($table, ['c_name' => 'es']);

        // only "games" contains "es"
        $this->assertSame([2], array_map('intval', self::column($rows, 'c_id')));
        $this->assertSame(1, $table->getFilteredRows());
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, mixed>>
     */
    private function getAllRows(Table $table, array $filters): array
    {
        return $this->tableService()->getRows($table, $this->postRequest($table, $filters, ['rowsPerPage' => '100']));
    }

    /**
     * @param list<int>            $expectedIds
     * @param array<string, mixed> $filters
     */
    private function assertFilteredIds(array $expectedIds, Table $table, array $filters, bool $sorted = true): void
    {
        $rows = $this->getAllRows($table, $filters);

        if ($sorted) {
            sort($expectedIds);
            $this->assertSame($expectedIds, self::sortedProductIds($rows));
        } else {
            $this->assertSame($expectedIds, self::productIds($rows));
        }
        $this->assertSame(\count($expectedIds), $table->getFilteredRows());
        $this->assertSame(25, $table->getTotalRows());
    }
}
