<?php

namespace Kilik\TableBundle\Services;

use Kilik\TableBundle\Components\TableInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Twig_Environment;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\FilterCheckbox;
use Kilik\TableBundle\Components\FilterSelect;
use Doctrine\ORM\Query;

abstract class AbstractTableService implements TableServiceInterface
{
    /**
     * Twig Service.
     *
     * @var Twig_Environment
     */
    protected $twig;

    /**
     * FormFactory Service.
     *
     * @var FormFactory
     */
    protected $formFactory;

    /**
     * TableService constructor.
     *
     * @param Twig_Environment $twig
     * @param FormFactory      $formFactory
     */
    public function __construct(Twig_Environment $twig, FormFactory $formFactory)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function form(TableInterface $table)
    {
        // prepare defaults values
        $defaultValues = [];
        foreach ($table->getAllFilters() as $filter) {
            if (!is_null($filter->getDefaultValue())) {
                $defaultValues[$filter->getName()] = $filter->getDefaultValue();
            }
        }

        $form = $this->formFactory->createNamedBuilder($table->getId().'_form', FormType::class, $defaultValues);
        //$this->formBuilder->set
        foreach ($table->getAllFilters() as $filter) {
            // selon le type de filtre
            switch ($filter::FILTER_TYPE) {
                case FilterCheckbox::FILTER_TYPE:
                    $form->add(
                        $filter->getName(),
                        \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class,
                        ['required' => false]
                    );
                    break;
                case FilterSelect::FILTER_TYPE:
                    /* @var FilterSelect $filter */
                    $form->add(
                        $filter->getName(),
                        \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class,
                        [
                            'required' => false,
                            'choices' => $filter->getChoices(),
                            'placeholder' => $filter->getPlaceholder(),
                            'group_by' => $filter->getChoicesGroupBy(),
                            'choice_label' => $filter->getChoiceLabel(),
                            'choice_value' => $filter->getChoiceValue(),
                        ]
                    );
                    break;
                case Filter::FILTER_TYPE:
                default:
                    $form->add(
                        $filter->getName(),
                        \Symfony\Component\Form\Extension\Core\Type\TextType::class,
                        [
                            'required' => false,
                        ]
                    );
                    break;
            }
        }

        // append special inputs (used for export csv for exemple)
        $form->add('sortColumn', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, ['required' => false]);
        $form->add('sortReverse', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, ['required' => false]);

        return $form->getForm()->createView();
    }

    /**
     * {@inheritdoc}
     */
    public function createFormView(TableInterface $table)
    {
        return $table->setFormView($this->form($table));
    }

    /**
     * Export (selection by filters) as a CSV buffer.
     *
     * @param TableInterface $table
     * @param Request        $request
     *
     * @return string
     */
    public function exportAsCsv(TableInterface $table, Request $request)
    {
        // execute query with filters, without pagination, only scalar results
        $rows = $this->getRows($table, $request, false, false);

        $buffer = '';
        // first line: keys
        if (count($rows) > 0) {
            foreach ($table->getColumns() as $column) {
                $buffer .= $column->getName().';';
            }
            $buffer .= "\n";
        }

        foreach ($rows as $row) {
            foreach ($table->getColumns() as $column) {
                $buffer .= $column->getExportValue($row, $rows).';';
            }
            $buffer .= "\n";
        }

        return $buffer;
    }

    /**
     * Handle the user request and return the JSON response (with pagination).
     *
     * @param TableInterface $table
     * @param Request        $request
     *
     * @return Response
     */
    public function handleRequest(TableInterface $table, Request $request)
    {
        // execute query with filters
        $rows = $this->getRows($table, $request);

        // params for twig parts
        $twigParams = [
            'table' => $table,
            'rows' => $rows,
        ];

        $template = $this->twig->loadTemplate($table->getTemplate());

        $responseParams = [
            'page' => $table->getPage(),
            'rowsPerPage' => $table->getRowsPerPage(),
            'totalRows' => $table->getTotalRows(),
            'filteredRows' => $table->getFilteredRows(),
            'lastPage' => $table->getLastPage(),
            'tableBody' => $template->renderBlock(
                'tableBody',
                array_merge($twigParams, ['tableRenderBody' => true], $table->getTemplateParams())
            ),
            //"tableFoot"=>$template->renderBlock("tableFoot", $twigParams),
            'tableStats' => $template->renderBlock(
                'tableStatsAjax',
                array_merge($twigParams, ['tableRenderStats' => true])
            ),
            'tablePagination' => $template->renderBlock(
                'tablePaginationAjax',
                array_merge($twigParams, ['tableRenderPagination' => true])
            ),
        ];

        // encode response
        $response = new Response(json_encode($responseParams));

        return $response;
    }
}
