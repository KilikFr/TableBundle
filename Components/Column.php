<?php

namespace Kilik\TableBundle\Components;

class Column
{

    const TYPE_DEFAULT = "text";
    const TYPE_TEXT    = "text";
    const TYPES        = [self::TYPE_TEXT];

    /**
     * display formats
     */
    const FORMAT_DATE    = "date";
    const FORMAT_TEXT    = "text";
    const FORMAT_DEFAULT = self::FORMAT_TEXT;
    const FORMATS        = [self::FORMAT_DATE, self::FORMAT_TEXT];

    /**
     * filter on this column ?
     * 
     * @var Filter
     */
    private $filter;

    /**
     * Label of the column
     * 
     * @var string 
     */
    private $label;

    /**
     * Sort
     * 
     * @var array 
     */
    private $sort;

    /**
     * Reverse sort
     * 
     * @var array 
     */
    private $sortReverse;

    /**
     * Wich type (for default display transco)
     * 
     * @var string
     */
    private $type = self::TYPE_DEFAULT;

    /**
     *
     * @var string 
     */
    private $name;

    /**
     * Activate label translation ?
     * 
     * @var bool 
     */
    private $translateLabel = false;

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
     * 
     * @param Filter $filter
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
     * 
     * @return Filter
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * 
     * @param string $label
     * @return static
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Set name (scalar field)
     * 
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name (scalar field)
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 
     * @param array $sort
     * @return static
     */
    public function setSort($sort)
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Get sort reversed, or not (if sortReverse is empty, auto revert all sort orders)
     * 
     * @param bool $reverse
     */
    public function getAutoSort($reverse)
    {
        if ($reverse) {
            if (count($this->sortReverse) == 0) {
                return $this->getAutoInvertedSort();
            }
            else {
                return $this->sortReverse;
            }
        }
        return $this->sort;
    }

    /**
     * Get sort, with auto inverted orders
     * 
     * @return array
     */
    public function getAutoInvertedSort()
    {
        $result = [];
        foreach ($this->getSort() as $sort=> $order) {
            $result[$sort] = ($order == "asc" ? "desc" : "asc");
        }
        return $result;
    }

    /**
     * 
     * @return array
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * 
     * @param array $sort
     * @return static
     */
    public function setSortReverse($sortReverse)
    {
        $this->sortReverse = $sortReverse;

        return $this;
    }

    /**
     * 
     * @return array
     */
    public function getSortReverse()
    {
        return $this->sortReverse;
    }

    /**
     * @return bool
     */
    public function sortable()
    {
        return !is_null($this->sort) || is_null($this->sortReverse);
    }

    /**
     * Set label to translation
     * 
     * @param bool $translate
     * @return static
     */
    public function setTranslateLabel($translate)
    {
        $this->translateLabel = $translate;

        return $this;
    }

    /**
     * 
     * @return bool
     */
    public function getTranslateLabel()
    {
        return $this->translateLabel;
    }

    /**
     * @param string $displayFormat
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
     * 
     * @return string
     */
    public function getDisplayFormat()
    {
        return $this->displayFormat;
    }

    /**
     * @param array $displayFormatParams
     * @return static
     */
    public function setDisplayFormatParams($displayFormatParams)
    {
        $this->displayFormatParams = $displayFormatParams;

        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getDisplayFormatParams()
    {
        return $this->displayFormatParams;
    }

    /**
     * Get the formatted value to display
     * 
     * @param array $row : the row to display
     */
    public function getValue($row)
    {
        if (isset($row[$this->getName()])) {
            $rawValue = $row[$this->getName()];
            switch ($this->getDisplayFormat()) {
                case static::FORMAT_DATE:
                    $formatParams = $this->getDisplayFormatParams();
                    if (is_null($formatParams)) {
                        $formatParams = "Y-m-d H:i:s";
                    }
                    if (!is_null($rawValue) && is_object($rawValue) && get_class($rawValue) == "DateTime") {
                        return $rawValue->format($formatParams);
                    }
                    else {
                        return "bad argument";
                    }
                    break;
                case static::FORMAT_TEXT:
                default:
                    return $rawValue;
                    break;
            }
        }
        else {
            return "";
        }
    }

}
