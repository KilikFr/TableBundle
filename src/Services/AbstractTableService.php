<?php

namespace Kilik\TableBundle\Services;

use Doctrine\ORM\Query;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\TableInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig_Environment;

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
        $defaultValues = array();
        foreach ($table->getAllFilters() as $filter) {
            if (null !== $filter->getDefaultValue()) {
                $defaultValues[$filter->getName()] = $filter->getDefaultValue();
            }
        }

        $form = $this->formFactory->createNamedBuilder($table->getId().'_form', FormType::class, $defaultValues);
        //$this->formBuilder->set
        foreach ($table->getAllFilters() as $filter) {
            $form->add(
                $filter->getName(),
                $filter->getInput(),
                $filter->getOptions()
            );
        }

        // append special inputs (used for export csv for exemple)
        $form->add('sortColumn', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, array('required' => false));
        $form->add('sortReverse', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, array('required' => false));

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
        $twigParams = array(
            'table' => $table,
            'rows' => $rows,
        );

        $template = $this->twig->loadTemplate($table->getTemplate());

        $responseParams = array(
            'page' => $table->getPage(),
            'rowsPerPage' => $table->getRowsPerPage(),
            'totalRows' => $table->getTotalRows(),
            'filteredRows' => $table->getFilteredRows(),
            'lastPage' => $table->getLastPage(),
            'tableBody' => $template->renderBlock(
                'tableBody',
                array_merge($twigParams, array('tableRenderBody' => true), $table->getTemplateParams())
            ),
            //"tableFoot"=>$template->renderBlock("tableFoot", $twigParams),
            'tableStats' => $template->renderBlock(
                'tableStatsAjax',
                array_merge($twigParams, array('tableRenderStats' => true))
            ),
            'tablePagination' => $template->renderBlock(
                'tablePaginationAjax',
                array_merge($twigParams, array('tableRenderPagination' => true))
            ),
        );

        // encode response
        $response = new Response(json_encode($responseParams));

        return $response;
    }

    /**
     * @param Request        $request
     * @param TableInterface $table
     *
     * @return mixed
     */
    public function getSelectedRows(Request $request, TableInterface $table)
    {
        $identifiers = $request->request->get($table->getSelectionFormKey());
        $entities = $this->loadRowsById($table, $identifiers);

        return $entities;
    }
}
