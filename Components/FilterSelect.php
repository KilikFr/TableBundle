<?php

namespace Kilik\TableBundle\Components;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Class FilterSelect.
 *
 * @deprecated since version 1.1 to be removed in 2.0
 */
class FilterSelect extends Filter
{
    /**
     * {@inheritDoc}
     */
    protected $input = ChoiceType::class;

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
     * Domain to translate place holder and values.
     *
     * @var string
     */
    private $translationDomain = 'messages';

    /**
     * Domain to translate values.
     *
     * @var string
     */
    private $choiceTranslationDomain = 'messages';

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

    /**
     * @param string|bool $translationDomain
     *
     * @return static
     */
    public function setTranslationDomain($translationDomain)
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    /**
     * @return string|bool
     */
    public function getTranslationDomain()
    {
        return $this->translationDomain;
    }

    /**
     * @param string|bool $choiceTranslationDomain
     *
     * @return static
     */
    public function setChoiceTranslationDomain($choiceTranslationDomain)
    {
        $this->choiceTranslationDomain = $choiceTranslationDomain;

        return $this;
    }

    /**
     * @return string|bool
     */
    public function getChoiceTranslationDomain()
    {
        return $this->choiceTranslationDomain;
    }

    /**
     * Disable translation domains.
     *
     * @return static
     */
    public function disableTranslation()
    {
        $this->setTranslationDomain(false);
        $this->setChoiceTranslationDomain(false);

        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        $options = array_merge(
            array(
                'required' => false,
                'choices' => $this->getChoices(),
                'placeholder' => $this->getPlaceholder(),
                'group_by' => $this->getChoicesGroupBy(),
                'choice_label' => $this->getChoiceLabel(),
                'choice_value' => $this->getChoiceValue(),
                'translation_domain' => $this->getTranslationDomain(),
                'choice_translation_domain' => $this->getChoiceTranslationDomain(),
            ),
            $this->options
        );

        return $options;
    }
}
