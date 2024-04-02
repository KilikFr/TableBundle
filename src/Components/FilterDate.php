<?php

namespace Kilik\TableBundle\Components;

use Doctrine\ORM\QueryBuilder;

class FilterDate extends Filter
{
    public const INPUT_FORMAT_BIG_ENDIAN = 'Y-m-d H:i:s'; // Accepts also input with "/" : Y/m/d H:i:s
    public const INPUT_FORMAT_LITTLE_ENDIAN = 'd-m-Y H:i:s';  // Accepts also input with "/" : d/m/Y H:i:s

    public const INPUT_FORMATS = [
        self::INPUT_FORMAT_BIG_ENDIAN,
        self::INPUT_FORMAT_LITTLE_ENDIAN,
    ];

    public const TYPES = [
        self::TYPE_EQUAL, // `=2014-07-27` expects BETWEEN 2014-07-27 00:00:00 AND 2014-07-27 23:59:59
        self::TYPE_NOT_EQUAL, // `!=2014-07-27` expects NOT BETWEEN 2014-07-27 00:00:00 AND 2014-07-27 23:59:59
        self::TYPE_GREATER, // `>2014-07-27` expects > 2014-07-27 23:59:59
        self::TYPE_GREATER_OR_EQUAL, // `>=2014-07-27` expects >= 2014-07-27 00:00:00
        self::TYPE_LESS, // `<2014-07-27` expects < 2014-07-27 00:00:00
        self::TYPE_LESS_OR_EQUAL, // `<=2014-07-27` expects < 2014-07-27 23:59:59
    ];

    protected string $inputFormat = self::INPUT_FORMAT_BIG_ENDIAN;

    public function __construct()
    {
        $this->setQueryPartBuilder(function (Filter $filter, Table $table, QueryBuilder $qb, $rawInput) {
            $rawInput = trim((string) $rawInput);
            if ('' === $rawInput) {
                return;
            }

            list($operator, $input) = $this->getOperatorAndValue($rawInput);
            if ('' === (string) $input) {
                switch ($operator) {
                    case static::TYPE_EQUAL:
                        $qb->andWhere($this->getField().' IS NULL');

                        return;
                    case static::TYPE_NOT_EQUAL:
                        $qb->andWhere($this->getField().' IS NOT NULL');

                        return;
                }
            }
            if (!in_array($operator, static::TYPES)) {
                $operator = static::TYPE_EQUAL;
            }

            if (null === $period = $this->getPeriodFromInput($input)) {
                $qb->andWhere('0=1');

                return;
            }

            $query = $this->buildWhereQuery($operator);
            if (false !== strpos($query, $this->buildPeriodStartParameterName())) {
                $qb->setParameter($this->buildPeriodStartParameterName(), $period[0]);
            }
            if (false !== strpos($query, $this->buildPeriodEndParameterName())) {
                $qb->setParameter($this->buildPeriodEndParameterName(), $period[1]);
            }

            $qb->andWhere($query);
        });
    }

    public function setDataFormat($dataFormat)
    {
        throw new \LogicException('FilterDate data format cannot be modified.');
    }

    public function setInputFormatter($formatter)
    {
        throw new \LogicException('FilterDate input formatter cannot be modified.');
    }

    public function getInputFormat(): string
    {
        return $this->inputFormat;
    }

    public function setInputFormat(string $inputFormat): self
    {
        if (!in_array($inputFormat, static::INPUT_FORMATS)) {
            throw new \InvalidArgumentException('Unexpected input format');
        }
        $this->inputFormat = $inputFormat;

        return $this;
    }

    protected function getPeriodFromInput(string $input): ?array
    {
        $input = trim(str_replace('/', '-', $input));
        $format = $this->inputFormat;

        // Complete datetime
        if (false !== $date = date_create_immutable_from_format($format, $input)) {
            return $date->getLastErrors() ? null : [$date, $date];
        }

        // Without second
        $format = trim(str_replace('s', '', $format), '-: ');
        if (false !== $date = date_create_immutable_from_format('!'.$format, $input)) {
            return $date->getLastErrors() ? null : [$date, $date->modify('+59 seconds')];
        }

        // Without minute
        $format = trim(str_replace('i', '', $format), '-: ');
        if (false !== $date = date_create_immutable_from_format('!'.$format, $input)) {
            return $date->getLastErrors() ? null : [$date, $date->modify('+1 hour -1 second')];
        }

        // Only date (without time)
        $format = trim(str_replace('H', '', $format), '-: ');
        if (false !== $date = date_create_immutable_from_format('!'.$format, $input)) {
            return $date->getLastErrors() ? null : [$date, $date->modify('+1 day -1 second')];
        }

        // Only month and year
        $format = trim(str_replace('d', '', $format), '-: ');
        if (false !== $date = date_create_immutable_from_format('!'.$format, $input)) {
            return $date->getLastErrors() ? null : [$date, $date->modify('+1 month -1 second')];
        }

        // Only year
        $format = trim(str_replace('m', '', $format), '-: ');
        if (false !== $date = date_create_immutable_from_format('!'.$format, $input)) {
            return $date->getLastErrors() ? null : [$date, $date->modify('+1 year -1 second')];
        }

        return null;
    }

    protected function buildWhereQuery(string $operator): string
    {
        switch ($operator) {
            case static::TYPE_NOT_EQUAL:
                return $this->getField().' NOT BETWEEN :'.$this->buildPeriodStartParameterName().' AND :'.$this->buildPeriodEndParameterName();
            case static::TYPE_GREATER:
                return $this->getField().' > :'.$this->buildPeriodEndParameterName();
            case static::TYPE_GREATER_OR_EQUAL:
                return $this->getField().' >= :'.$this->buildPeriodStartParameterName();
            case static::TYPE_LESS:
                return $this->getField().' < :'.$this->buildPeriodStartParameterName();
            case static::TYPE_LESS_OR_EQUAL:
                return $this->getField().' <= :'.$this->buildPeriodEndParameterName();
            default:
            case static::TYPE_EQUAL:
                return $this->getField().' BETWEEN :'.$this->buildPeriodStartParameterName().' AND :'.$this->buildPeriodEndParameterName();
        }
    }

    protected function buildPeriodStartParameterName(): string
    {
        return 'filter_'.$this->getName().'_start';
    }

    protected function buildPeriodEndParameterName(): string
    {
        return 'filter_'.$this->getName().'_end';
    }
}
