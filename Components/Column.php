<?php

namespace Kilik\TableBundle\Components;

class Column
{
    const TYPE_DEFAULT = 'text';
    const TYPE_TEXT = 'text';
    const TYPES = [self::TYPE_TEXT];

    /**
     * display formats.
     */
    const FORMAT_DATE = 'date';
    const FORMAT_TEXT = 'text';
    const FORMAT_DEFAULT = self::FORMAT_TEXT;
    const FORMATS = [self::FORMAT_DATE, self::FORMAT_TEXT];

    /**
     * filter on this column ?
     *
     * @var Filter
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
     * @var array
     */
    private $sort;

    /**
     * Reverse sort.
     *
     * @var array
     */
    private $sortReverse = [];

    /**
     * Wich type (for default display transco).
     *
     * @var string
     */
    private $type = self::TYPE_DEFAULT;

    /**
     * @var string
     */
    private $name;

    /**
     * Domain for label translation ?
     *
     * @var bool
     */
    private $translateDomain = null;

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
     *
     * @var mixed
     */
    private $displayFormatParams = null;

    /**
     * callback for a custom display method.
     *
     * @var mixed
     */
    private $displayCallback = null;

    /**
     * callback for a custom export method.
     *
     * @var mixed
     */
    private $exportCallback = null;

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
     * @param Filter $filter
     *
     * @return static
     */
    public function setFilter(Filter $filter)
    {
        if (is_null($this->name)) {
            $this->name = $filter->getName();
        }
        $this->filter = $filter;

        return $this;
    }

    /**
     * @return Filter
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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set sort fields.
     *
     * @param array $sort
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
     * @return array
     */
    public function getAutoSort($reverse)
    {
        if ($reverse) {
            if (count($this->sortReverse) == 0) {
                return $this->getAutoInvertedSort();
            } else {
                return $this->sortReverse;
            }
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
            $result[$sort] = ($order == 'asc' ? 'desc' : 'asc');
        }

        return $result;
    }

    /**
     * Get sort fields.
     *
     * @return array
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * Set reversed sort fields.
     *
     * @param array $sortReverse
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
     * @param bool $translate
     *
     * @return static
     */
    public function setTranslateLabel($translate)
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
     *
     * @return bool
     */
    public function getTranslateLabel()
    {
        return !is_null($this->translateDomain);
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
     * @return bool
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
     * @param array $displayFormatParams
     *
     * @return static
     */
    public function setDisplayFormatParams($displayFormatParams)
    {
        $this->displayFormatParams = $displayFormatParams;

        return $this;
    }

    /**
     * Get display format parameters.
     *
     * @return string
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
     *
     * @return mixed
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
     *
     * @return mixed
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
     * @param       $row
     * @param array $rows
     *
     * @return string
     *
     * @throws \Exception
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
        if (!is_null($callback)) {
            if (!is_callable($callback)) {
                throw new \Exception('displayCallback is not callable');
            }

            return $callback($rawValue, $row, $rows);
        } else {
            switch ($this->getDisplayFormat()) {
                case static::FORMAT_DATE:
                    $formatParams = $this->getDisplayFormatParams();
                    if (is_null($formatParams)) {
                        $formatParams = 'Y-m-d H:i:s';
                    }
                    if (!is_null($rawValue) && is_object($rawValue) && get_class($rawValue) == 'DateTime') {
                        return $rawValue->format($formatParams);
                    } else {
                        return '';
                    }
                    break;
                case static::FORMAT_TEXT:
                default:
                    if (is_array($rawValue)) {
                        return implode(',', $rawValue);
                    } else {
                        return $rawValue;
                    }
                    break;
            }
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
     * @param array $row
     * @param array $rows
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getExportValue(array $row, array $rows = [])
    {
        if (isset($row[$this->getName()])) {
            $rawValue = $row[$this->getName()];
            // if a callback is set
            $callback = $this->getExportCallback();
            if (!is_null($callback)) {
                if (!is_callable($callback)) {
                    throw new \Exception('exportCallback is not callable');
                }

                return $callback($rawValue, $row, $rows);
            } else {
                switch ($this->getDisplayFormat()) {
                    case static::FORMAT_DATE:
                        $formatParams = $this->getDisplayFormatParams();
                        if (is_null($formatParams)) {
                            $formatParams = 'Y-m-d H:i:s';
                        }
                        if (!is_null($rawValue) && is_object($rawValue) && get_class($rawValue) == 'DateTime') {
                            return $rawValue->format($formatParams);
                        } else {
                            return '';
                        }
                        break;
                    case static::FORMAT_TEXT:
                    default:
                        if (is_array($rawValue)) {
                            return implode(',', $rawValue);
                        } else {
                            return $rawValue;
                        }
                        break;
                }
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
}
