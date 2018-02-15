<?php

namespace Kilik\TableBundle\Components;

class MassAction
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $class = '';

    /**
     * @var string
     */
    private $label;

    /**
     * @var string
     */
    private $action;

    /**
     * MassAction constructor.
     * @param string $name
     * @param string $label
     * @param string $class
     * @param string $action
     */
    public function __construct(
        $name,
        $label = 'action',
        $class = '',
        $action = ''
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->class = $class;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
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
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
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
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param string $class
     *
     * @return static
     */
    public function setClass($class)
    {
        $this->class = $class;
        return $this;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $action
     */
    public function setAction($action)
    {
        $this->action = $action;
    }
}
