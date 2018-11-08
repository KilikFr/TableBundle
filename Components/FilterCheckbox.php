<?php

namespace Kilik\TableBundle\Components;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

/**
 * Class FilterCheckbox.
 *
 * @deprecated since version 1.1 to be removed in 2.0
 */
class FilterCheckbox extends Filter
{
    /**
     * {@inheritDoc}
     */
    protected $input = CheckboxType::class;
}
