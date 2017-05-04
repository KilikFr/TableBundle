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
     * Choice Label Select callback (@see http://symfony.com/doc/current/reference/forms/types/choice.html#choice-label).
     *
     * @var callable
     */
    private $choiceLabel;

    /**
     * Choice Value Select callback (@see http://symfony.com/doc/current/reference/forms/types/choice.html#choice-value).
     *
     * @var callable
     */
    private $choiceValue;

    /**
     * Group By Select callback (@see http://symfony.com/doc/current/reference/forms/types/choice.html#group-by).
     *
     * @var callable
     */
    private $choicesGroupBy;

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

    /**
     * @param callable $choiceLabel
     *
     * @return static
     */
    public function setChoiceLabel($choiceLabel)
    {
        $this->choiceLabel = $choiceLabel;

        return $this;
    }

    /**
     * @return callable
     */
    public function getChoiceLabel()
    {
        return $this->choiceLabel;
    }

    /**
     * @param callable $choiceValue
     *
     * @return static
     */
    public function setChoiceValue($choiceValue)
    {
        $this->choiceValue = $choiceValue;

        return $this;
    }

    /**
     * @return callable
     */
    public function getChoiceValue()
    {
        return $this->choiceValue;
    }

    /**
     * @param callable|null $choicesGroupBy
     *
     * @return static
     */
    public function setChoicesGroupBy($choicesGroupBy)
    {
        $this->choicesGroupBy = $choicesGroupBy;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getChoicesGroupBy()
    {
        return $this->choicesGroupBy;
    }
}
