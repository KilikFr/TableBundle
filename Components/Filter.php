<?php

namespace Kilik\TableBundle\Components;

class Filter
{

    const TYPE_DEFAULT          = "like";
    const TYPE_LIKE             = "like";
    const TYPE_GREATER          = ">";
    const TYPE_GREATER_OR_EQUAL = ">=";
    const TYPE_LESS             = "<";
    const TYPE_LESS_OR_EQUAL    = "<=";
    const TYPES                 = [self::TYPE_LIKE, self::TYPE_GREATER, self::TYPE_GREATER_OR_EQUAL, self::TYPE_LESS, self::TYPE_LESS_OR_EQUAL];

    /**
     *
     * @var string 
     */
    private $name;

    /**
     *
     * @var string 
     */
    private $field;

    /**
     * This filter is a HAVING constraint ?
     * 
     * @var bool
     */
    private $having = false;

    /**
     * @var string
     */
    private $type = self::TYPE_DEFAULT;

    /**
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
     * 
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * 
     * @param string $field
     * @return static
     */
    public function setField($field)
    {
        $this->field = $field;

        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * 
     * @param string $type
     * @return static
     */
    public function setType($type)
    {
        if (!in_array($type, self::TYPES)) {
            throw new \InvalidArgumentException("bad type {$type}");
        }
        $this->type = $type;

        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * 
     * @param bool $having
     * @return static
     */
    public function setHaving($having)
    {
        $this->having = $having;

        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getHaving()
    {
        return $this->having;
    }

}
