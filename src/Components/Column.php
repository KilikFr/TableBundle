<?php

namespace Kilik\TableBundle\Components;

class Column
{
    public const TYPE_DEFAULT = 'text';
    public const TYPE_TEXT = 'text';
    public const TYPES = [self::TYPE_TEXT];

    /**
     * display formats.
     */
    public const FORMAT_DATE = 'date';
    public const FORMAT_TEXT = 'text';
    public const FORMAT_DEFAULT = self::FORMAT_TEXT;
    public const FORMATS = [self::FORMAT_DATE, self::FORMAT_TEXT];

    /**
     * filter on this column ?
     *
     * @var Filter|null
     */
    private $filter;

    /**
     * Label of the column.
     *
     * @var string
     */
    private $label;

    /**
     * Sort.
     *
     * @var array<string, string|\SortDirection>|null
     */
    private $sort;

    /**
     * Reverse sort.
     *
     * @var array
     */
    private $sortReverse = [];

    /**
     * @var string|null
     */
    private $name;

    /**
     * @var string|null
     */
    private $exportName;

    /**
     * Domain for label translation ?
     *
     * @var string|null
     */
    private $translateDomain;

    /**
     * Capitalize label ?
     *
     * @var bool
     */
    private $capitalize = true;

    /**
     * custom display method ?
     *
     * @var string
     */
    private $displayFormat = self::FORMAT_DEFAULT;

    /**
     * custom display params ?
     */
    private $displayFormatParams;

    /**
     * callback for a custom display method.
     */
    private $displayCallback;

    /**
     * callback for a custom export method.
     */
    private $exportCallback;

    /**
     * mode raw ?
     *
     * @var bool
     */
    private $raw = false;

    /**
     * hidden by default ?
     *
     * @var bool
     */
    private $hiddenByDefault = false;

    /**
     * hidden ?
     *
     * @var bool
     */
    private $hidden = false;

    /**
     * @var bool
     */
    private $useTotal = false;

    /**
     * @var null|mixed
     */
    private $total;

    /**
     * CSS Class.
     *
     * @var string
     */
    private $displayClass;

    /**
     * CSS Class.
     *
     * @var string
     */
    private $headerClass;

    /**
     * CSS Class.
     *
     * @var string
     */
    private $filterClass;

    /**
     * Custom cell template.
     *
     * @var string
     */
    private $cellTemplate;

    /**
     * @return static
     */
    public function setFilter(?Filter $filter = null)
    {
        if (null === $this->name && null !== $filter) {
            $this->name = $filter->getName();
        }
        $this->filter = $filter;

        return $this;
    }

    /**
     * @return Filter|null
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * Set column label.
     *
     * @param string $label
     *
     * @return static
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Get column label.
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set name (scalar field).
     *
     * @param string $name
     *
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name (scalar field).
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set export name.
     *
     * @return static
     */
    public function setExportName(string $exportName)
    {
        $this->exportName = $exportName;

        return $this;
    }

    /**
     * Get export name.
     */
    public function getExportName(): ?string
    {
        return $this->exportName;
    }

    /**
     * Set sort fields, as field => direction ("asc", "desc" or a \SortDirection case).
     *
     * @param array<string, string|\SortDirection> $sort
     *
     * @return static
     */
    public function setSort($sort)
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Get sort reversed, or not (if sortReverse is empty, auto revert all sort orders).
     *
     * @param bool $reverse
     *
     * @return array<string, string|\SortDirection>|null
     */
    public function getAutoSort($reverse)
    {
        if ($reverse) {
            if (0 == count($this->sortReverse)) {
                return $this->getAutoInvertedSort();
            }

            return $this->sortReverse;
        }

        return $this->sort;
    }

    /**
     * Get sort, with auto inverted orders.
     *
     * @return array
     */
    public function getAutoInvertedSort()
    {
        $result = [];
        foreach ($this->getSort() as $sort => $order) {
            if ($order instanceof \SortDirection) {
                $result[$sort] = \SortDirection::Ascending === $order ? \SortDirection::Descending : \SortDirection::Ascending;
                continue;
            }
            $order = strtolower($order);
            $result[$sort] = ('asc' == $order ? 'desc' : 'asc');
        }

        return $result;
    }

    /**
     * Get sort fields.
     *
     * @return array<string, string|\SortDirection>|null
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * Set reversed sort fields, as field => direction ("asc", "desc" or a \SortDirection case).
     *
     * @param array<string, string|\SortDirection> $sortReverse
     *
     * @return static
     */
    public function setSortReverse($sortReverse)
    {
        $this->sortReverse = $sortReverse;

        return $this;
    }

    /**
     * Get reversed sort fields.
     *
     * @return array
     */
    public function getSortReverse()
    {
        return $this->sortReverse;
    }

    /**
     * Column is sortable ?
     *
     * @return bool
     */
    public function sortable()
    {
        return !empty($this->sort) || !empty($this->sortReverse);
    }

    /**
     * Set label to translation.
     *
     * @return static
     */
    public function setTranslateLabel(bool $translate)
    {
        if ($translate) {
            $this->translateDomain = 'messages';
        } else {
            $this->translateDomain = null;
        }

        return $this;
    }

    /**
     * Column label should be translated ?
     */
    public function getTranslateLabel(): bool
    {
        return null !== $this->translateDomain;
    }

    /**
     * Set label domain translation.
     *
     * @param string $domain
     *
     * @return static
     */
    public function setTranslateDomain($domain)
    {
        $this->translateDomain = $domain;

        return $this;
    }

    /**
     * Column label translation domain.
     *
     * @return string|null
     */
    public function getTranslateDomain()
    {
        return $this->translateDomain;
    }

    /**
     * Set the display format.
     *
     * @param string $displayFormat
     *
     * @return static
     */
    public function setDisplayFormat($displayFormat)
    {
        if (!in_array($displayFormat, static::FORMATS)) {
            throw new \InvalidArgumentException("bad format '{$displayFormat}'");
        }
        $this->displayFormat = $displayFormat;

        return $this;
    }

    /**
     * Get the display format.
     *
     * @return string
     */
    public function getDisplayFormat()
    {
        return $this->displayFormat;
    }

    /**
     * Set the raw option (for raw twig rendering).
     *
     * @param bool $raw
     *
     * @return static
     */
    public function setRaw($raw)
    {
        $this->raw = $raw;

        return $this;
    }

    /**
     * Get the raw option.
     *
     * @return bool
     */
    public function getRaw()
    {
        return $this->raw;
    }

    /**
     * Set display format parameters.
     *
     * @return static
     */
    public function setDisplayFormatParams($displayFormatParams)
    {
        $this->displayFormatParams = $displayFormatParams;

        return $this;
    }

    public function setTotal($total)
    {
        $this->total = $total;

        return $this;
    }

    public function useTotal()
    {
        $this->useTotal = true;

        return $this;
    }

    public function isUseTotal()
    {
        return $this->useTotal;
    }

    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Get display format parameters.
     */
    public function getDisplayFormatParams()
    {
        return $this->displayFormatParams;
    }

    /**
     * Set display callback method.
     *
     * @param mixed $callback : the function or [object,method], that accepts 3 parameters (cell value, row values,
     *                        rows)
     *
     * @return static
     */
    public function setDisplayCallback($callback)
    {
        $this->displayCallback = $callback;

        return $this;
    }

    /**
     * Get display callback method.
     */
    public function getDisplayCallback()
    {
        return $this->displayCallback;
    }

    /**
     * Set export callback method.
     *
     * @param mixed $callback : the function or [object,method], that accepts 3 parameters (cell value, row values,
     *                        rows)
     *
     * @return static
     */
    public function setExportCallback($callback)
    {
        $this->exportCallback = $callback;

        return $this;
    }

    /**
     * Get export callback method.
     */
    public function getExportCallback()
    {
        return $this->exportCallback;
    }

    /**
     * Callback sample.
     *
     * @param mixed $value : the column value (the object or a field)
     * @param array $row   : the row values
     * @param array $rows  : the rows values (of the page)
     *
     * @return string
     */
    public function sampleCallback($value, $row, $rows)
    {
        // this sample just return the value, but could do many more
        return (string) $value;
    }

    /**
     * Set hidden by default.
     *
     * @param bool $hidden
     *
     * @return static
     */
    public function setHiddenByDefault($hidden)
    {
        $this->hiddenByDefault = $hidden;

        return $this;
    }

    /**
     * Get hidden by default.
     *
     * @return bool
     */
    public function getHiddenByDefault()
    {
        return $this->hiddenByDefault;
    }

    /**
     * Set hidden.
     *
     * @param bool $hidden
     *
     * @return static
     */
    public function setHidden($hidden)
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Get hidden.
     *
     * @return bool
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Get the formatted value to display.
     *
     * priority formatter methods:
     * - callback
     * - known formats
     * - default (raw text)
     *
     * @throws \Exception
     * @return string
     */
    public function getValue(array $row, array $rows = [])
    {
        if (isset($row[$this->getName()])) {
            $rawValue = $row[$this->getName()];
        } else {
            $rawValue = null;
        }
        // if a callback is set
        $callback = $this->getDisplayCallback();
        if (null !== $callback) {
            if (!is_callable($callback)) {
                throw new \Exception('displayCallback is not callable');
            }

            return $callback($rawValue, $row, $rows);
        }
        switch ($this->getDisplayFormat()) {
            case static::FORMAT_DATE:
                $formatParams = $this->getDisplayFormatParams();
                if (null === $formatParams) {
                    $formatParams = 'Y-m-d H:i:s';
                }
                if (null !== $rawValue && is_object($rawValue) && $rawValue instanceof \DateTimeInterface) {
                    return $rawValue->format($formatParams);
                }

                return '';
            case static::FORMAT_TEXT:
            default:
                if (is_array($rawValue)) {
                    return implode(',', $rawValue);
                }

                return $rawValue;
        }
    }

    /**
     *  Get the formatted value to export (used by CSV export).
     *
     * priority formatter methods:
     * - callback
     * - known formats
     * - default (raw text)
     *
     * @throws \Exception
     * @return string
     */
    public function getExportValue(array $row, array $rows = [])
    {
        if (isset($row[$this->getName()])) {
            $rawValue = $row[$this->getName()];
            // if a callback is set
            $callback = $this->getExportCallback();
            if (null !== $callback) {
                if (!is_callable($callback)) {
                    throw new \Exception('exportCallback is not callable');
                }

                return $callback($rawValue, $row, $rows);
            }
            switch ($this->getDisplayFormat()) {
                case static::FORMAT_DATE:
                    $formatParams = $this->getDisplayFormatParams();
                    if (null === $formatParams) {
                        $formatParams = 'Y-m-d H:i:s';
                    }
                    if (is_object($rawValue) && $rawValue instanceof \DateTimeInterface) {
                        return $rawValue->format($formatParams);
                    }

                    return '';
                case static::FORMAT_TEXT:
                default:
                    if (is_array($rawValue)) {
                        return implode(',', $rawValue);
                    }

                    return $rawValue;
            }
        } else {
            return '';
        }
    }

    /**
     * Enable/Disable the capitalize filter.
     *
     * @param bool $capitalize
     *
     * @return static
     */
    public function setCapitalize($capitalize = true)
    {
        $this->capitalize = $capitalize;

        return $this;
    }

    /**
     * Get the capitalize filter status.
     *
     * @return bool
     */
    public function getCapitalize()
    {
        return $this->capitalize;
    }

    /**
     * @param string $displayClass
     *
     * @return static
     */
    public function setDisplayClass($displayClass)
    {
        $this->displayClass = $displayClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getDisplayClass()
    {
        return $this->displayClass;
    }

    /**
     * @param string $headerClass
     *
     * @return static
     */
    public function setHeaderClass($headerClass)
    {
        $this->headerClass = $headerClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getHeaderClass()
    {
        return $this->headerClass;
    }

    /**
     * @param string $filterClass
     *
     * @return static
     */
    public function setFilterClass($filterClass)
    {
        $this->filterClass = $filterClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilterClass()
    {
        return $this->filterClass;
    }

    public function setCellTemplate(string $cellTemplate)
    {
        $this->cellTemplate = $cellTemplate;

        return $this;
    }

    public function getCellTemplate(): ?string
    {
        return $this->cellTemplate;
    }
}
