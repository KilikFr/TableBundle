<?php

namespace Kilik\TableBundle\Components;

class Column
{

    const TYPE_DEFAULT = "text";
    const TYPE_TEXT    = "text";
    const TYPES        = [self::TYPE_TEXT];

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

}
