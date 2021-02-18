<?php

namespace Kilik\TableBundle\Components;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class FilterCheckbox extends Filter
{
    /**
     * {@inheritDoc}
     */
    protected $input = CheckboxType::class;
}
