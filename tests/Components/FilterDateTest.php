<?php

namespace Components;

use Doctrine\ORM\QueryBuilder;
use Kilik\TableBundle\Components\FilterDate;
use Kilik\TableBundle\Components\Table;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\TestCase;

class FilterDateTest extends TestCase
{
    public function testGetPeriodFromInput()
    {
        $assertPeriodFromInput = function (FilterDate $filter, string $input, string $expectedStart, string $expectedEnd) {
            static $class, $method;
            if (null === $class) {
                $class = new \ReflectionClass(FilterDate::class);
                $method = $class->getMethod('getPeriodFromInput');
                $method->setAccessible(true);
            }

            $result = $method->invoke($filter, $input);
            $this->assertEquals($result[0], date_create_immutable($expectedStart));
            $this->assertEquals($result[1], date_create_immutable($expectedEnd));
        };

        $filter = (new FilterDate())->setInputFormat(FilterDate::INPUT_FORMAT_BIG_ENDIAN);
        $assertPeriodFromInput($filter, '1802-02-26 10:25:26', '1802-02-26 10:25:26', '1802-02-26 10:25:26');
        $assertPeriodFromInput($filter, '1802-02-26 10:25 ', '1802-02-26 10:25:00', '1802-02-26 10:25:59');
        $assertPeriodFromInput($filter, ' 1802-02-26 10', '1802-02-26 10:00:00', '1802-02-26 10:59:59');
        $assertPeriodFromInput($filter, ' 1802-02-26  ', '1802-02-26 00:00:00', '1802-02-26 23:59:59');
        $assertPeriodFromInput($filter, ' 1804-02', '1804-02-01 00:00:00', '1804-02-29 23:59:59');
        $assertPeriodFromInput($filter, ' 1989', '1989-01-01 00:00:00', '1989-12-31 23:59:59');
        $assertPeriodFromInput($filter, '2089-07-21 21:00:22', '2089-07-21 21:00:22', '2089-07-21 21:00:22');
        $assertPeriodFromInput($filter, '2089-07-21 15:00', '2089-07-21 15:00:00', '2089-07-21 15:00:59');
        $assertPeriodFromInput($filter, '2089-07-21 13', '2089-07-21 13:00:00', '2089-07-21 13:59:59');
        $assertPeriodFromInput($filter, '2089-07-21', '2089-07-21 00:00:00', '2089-07-21 23:59:59');
        $assertPeriodFromInput($filter, '2089-07', '2089-07-01 00:00:00', '2089-07-31 23:59:59');
        $assertPeriodFromInput($filter, '2089', '2089-01-01 00:00:00', '2089-12-31 23:59:59');

        $filter = (new FilterDate())->setInputFormat(FilterDate::INPUT_FORMAT_LITTLE_ENDIAN);
        $assertPeriodFromInput($filter, '21/11/1694 10:25:26', '1694-11-21 10:25:26', '1694-11-21 10:25:26');
        $assertPeriodFromInput($filter, '21/11/1694 10:25 ', '1694-11-21 10:25:00', '1694-11-21 10:25:59');
        $assertPeriodFromInput($filter, ' 21-11-1694 10', '1694-11-21 10:00:00', '1694-11-21 10:59:59');
        $assertPeriodFromInput($filter, ' 21/11/1694  ', '1694-11-21 00:00:00', '1694-11-21 23:59:59');
        $assertPeriodFromInput($filter, ' 11-1694', '1694-11-01 00:00:00', '1694-11-30 23:59:59');
        $assertPeriodFromInput($filter, ' 1517', '1517-01-01 00:00:00', '1517-12-31 23:59:59');
        $assertPeriodFromInput($filter, '23/01/2091 09:43:22', '2091-01-23 09:43:22', '2091-01-23 09:43:22');
        $assertPeriodFromInput($filter, '23-01-2091 09:43', '2091-01-23 09:43:00', '2091-01-23 09:43:59');
        $assertPeriodFromInput($filter, '23/01/2091 09', '2091-01-23 09:00:00', '2091-01-23 09:59:59');
        $assertPeriodFromInput($filter, '23/01/2091', '2091-01-23 00:00:00', '2091-01-23 23:59:59');
        $assertPeriodFromInput($filter, '01-2091', '2091-01-01 00:00:00', '2091-01-31 23:59:59');
        $assertPeriodFromInput($filter, '2091', '2091-01-01 00:00:00', '2091-12-31 23:59:59');
    }

    public function testGetPeriodFromInvalidInput()
    {
        $class = new \ReflectionClass(FilterDate::class);
        $method = $class->getMethod('getPeriodFromInput');
        $method->setAccessible(true);

        $filter = (new FilterDate())->setInputFormat(FilterDate::INPUT_FORMAT_BIG_ENDIAN);
        $this->assertNull($method->invoke($filter, '2024ZZ'));
        $this->assertNull($method->invoke($filter, '2023-02-29'));
        $this->assertNull($method->invoke($filter, '2024-02-30'));
        $this->assertNull($method->invoke($filter, '2024/28/02'));
        $this->assertNull($method->invoke($filter, '2024-02-28 26:00:00'));
        $this->assertNull($method->invoke($filter, '2024-02-28 12:65:00'));
        $this->assertNull($method->invoke($filter, '23/01/1991 09:00:00'));
        $this->assertNull($method->invoke($filter, 'Murs, ville, Et port. Asile De mort,'));

        $filter = (new FilterDate())->setInputFormat(FilterDate::INPUT_FORMAT_LITTLE_ENDIAN);
        $this->assertNull($method->invoke($filter, '202A'));
        $this->assertNull($method->invoke($filter, '29/02/2023'));
        $this->assertNull($method->invoke($filter, '30/02/2024'));
        $this->assertNull($method->invoke($filter, '02-28-2024'));
        $this->assertNull($method->invoke($filter, '28/02/2024 24:29:59'));
        $this->assertNull($method->invoke($filter, '28/02/2024 09:00:68'));
        $this->assertNull($method->invoke($filter, '1991-01-23'));
        $this->assertNull($method->invoke($filter, 'Mer grise Où brise La brise, Tout dort.'));
    }

    public function testBuildWhereQuery()
    {
        $assertQuery = function (FilterDate $filter, string $operator, string $expected) {
            static $class, $method;
            if (null === $class) {
                $class = new \ReflectionClass(FilterDate::class);
                $method = $class->getMethod('buildWhereQuery');
                $method->setAccessible(true);
            }

            $result = $method->invoke($filter, $operator);
            $this->assertEquals($result, $expected);
        };

        $filter = (new FilterDate())->setName('zz')->setField('f.createdAt');
        $assertQuery($filter, '', 'f.createdAt BETWEEN :filter_zz_start AND :filter_zz_end');
        $assertQuery($filter, FilterDate::TYPE_EQUAL, 'f.createdAt BETWEEN :filter_zz_start AND :filter_zz_end');
        $assertQuery($filter, FilterDate::TYPE_NOT_EQUAL, 'f.createdAt NOT BETWEEN :filter_zz_start AND :filter_zz_end');
        $assertQuery($filter, FilterDate::TYPE_GREATER, 'f.createdAt > :filter_zz_end');
        $assertQuery($filter, FilterDate::TYPE_GREATER_OR_EQUAL, 'f.createdAt >= :filter_zz_start');
        $assertQuery($filter, FilterDate::TYPE_LESS, 'f.createdAt < :filter_zz_start');
        $assertQuery($filter, FilterDate::TYPE_LESS_OR_EQUAL, 'f.createdAt <= :filter_zz_end');
    }

    public function testQueryPartBuilder()
    {
        $assert = function (FilterDate $filter, string $input, string $expectedQuery, array $expectedParameters) {
            $qb = $this->getMockBuilder(QueryBuilder::class)->disableOriginalConstructor()->getMock();
            $qb->expects($this->once())->method('andWhere')->with($this->equalTo($expectedQuery));

            $consecutiveParameters = [];
            foreach ($expectedParameters as $key => $rawDate) {
                $consecutiveParameters[] = [$key, date_create_immutable($rawDate)];
            }

            $qb->expects(new InvokedCount(count($expectedParameters)))->method('setParameter')->withConsecutive(...$consecutiveParameters);
            $filter->getQueryPartBuilder()($filter, new Table(), $qb, $input);
        };

        $filter = (new FilterDate())->setInputFormat(FilterDate::INPUT_FORMAT_BIG_ENDIAN)->setName('zz')->setField('yy');
        $assert($filter, '2024-02-28', 'yy BETWEEN :filter_zz_start AND :filter_zz_end', ['filter_zz_start' => '2024-02-28 00:00:00', 'filter_zz_end' => '2024-02-28 23:59:59']);
        $assert($filter, '=2024-01-31', 'yy BETWEEN :filter_zz_start AND :filter_zz_end', ['filter_zz_start' => '2024-01-31 00:00:00', 'filter_zz_end' => '2024-01-31 23:59:59']);
        $assert($filter, '!=2024-01-31', 'yy NOT BETWEEN :filter_zz_start AND :filter_zz_end', ['filter_zz_start' => '2024-01-31 00:00:00', 'filter_zz_end' => '2024-01-31 23:59:59']);
        $assert($filter, '>2024-01-31', 'yy > :filter_zz_end', ['filter_zz_end' => '2024-01-31 23:59:59']);
        $assert($filter, '>=2024-01-31', 'yy >= :filter_zz_start', ['filter_zz_start' => '2024-01-31 00:00:00']);
        $assert($filter, '<2024-01-31', 'yy < :filter_zz_start', ['filter_zz_start' => '2024-01-31 00:00:00']);
        $assert($filter, '<=2024-01-31', 'yy <= :filter_zz_end', ['filter_zz_end' => '2024-01-31 23:59:59']);
        $assert($filter, '<=20240131', '0=1', []);
        $assert($filter, '=djobi', '0=1', []);
        $assert($filter, '=', 'yy IS NULL', []);
        $assert($filter, '!=', 'yy IS NOT NULL', []);

        $filter = (new FilterDate())->setInputFormat(FilterDate::INPUT_FORMAT_LITTLE_ENDIAN)->setName('zz')->setField('yy');
        $assert($filter, '28-02-2024', 'yy BETWEEN :filter_zz_start AND :filter_zz_end', ['filter_zz_start' => '2024-02-28 00:00:00', 'filter_zz_end' => '2024-02-28 23:59:59']);
        $assert($filter, '=31-01-2024', 'yy BETWEEN :filter_zz_start AND :filter_zz_end', ['filter_zz_start' => '2024-01-31 00:00:00', 'filter_zz_end' => '2024-01-31 23:59:59']);
        $assert($filter, '!=31-01-2024', 'yy NOT BETWEEN :filter_zz_start AND :filter_zz_end', ['filter_zz_start' => '2024-01-31 00:00:00', 'filter_zz_end' => '2024-01-31 23:59:59']);
        $assert($filter, '>31-01-2024', 'yy > :filter_zz_end', ['filter_zz_end' => '2024-01-31 23:59:59']);
        $assert($filter, '>=31-01-2024', 'yy >= :filter_zz_start', ['filter_zz_start' => '2024-01-31 00:00:00']);
        $assert($filter, '<31-01-2024', 'yy < :filter_zz_start', ['filter_zz_start' => '2024-01-31 00:00:00']);
        $assert($filter, '<=31-01-2024', 'yy <= :filter_zz_end', ['filter_zz_end' => '2024-01-31 23:59:59']);
        $assert($filter, '>31012024', '0=1', []);
        $assert($filter, '!=djoba', '0=1', []);
        $assert($filter, '=', 'yy IS NULL', []);
        $assert($filter, '!=', 'yy IS NOT NULL', []);
    }
}
