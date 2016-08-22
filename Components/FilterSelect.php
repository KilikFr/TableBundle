<?php

namespace Kilik\TableBundle\Components;

class FilterSelect extends Filter
{
    /**
     * Type d'input.
     */
    const FILTER_TYPE = 'select';

    /**
     * Liste des valeurs du select.
     * 
     * @var array
     */
    private $choices;

    /**
     * Placeholder.
     * 
     * @var string
     */
    private $placeholder;

    /**
     * Set the choices.
     * 
     * @param array $choices
     *
     * @return static
     */
    public function setChoices($choices)
    {
        $this->choices = $choices;

        return $this;
    }

    /**
     * Get the choices.
     * 
     * @return array
     */
    public function getChoices()
    {
        return $this->choices;
    }

    /**
     * Set the placeholder.
     * 
     * @param string $placeholder
     *
     * @return static
     */
    public function setPlaceholder($placeholder)
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Get the placeholder.
     * 
     * @return string
     */
    public function getPlaceholder()
    {
        return $this->placeholder;
    }
}
