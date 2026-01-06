<?php

namespace Kilik\TableBundle\Services;

use Kilik\TableBundle\Components\TableInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

abstract class AbstractTableService implements TableServiceInterface
{
    /**
     * Twig Service.
     *
     * @var Environment
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
     * @param Environment $twig
     * @param FormFactory $formFactory
     */
    public function __construct(Environment $twig, FormFactory $formFactory)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function form(TableInterface $table, array $data = array())
    {
        // prepare defaults values
        $defaultValues = array();
        foreach ($table->getAllFilters() as $filter) {
            if (null !== $filter->getDefaultValue()) {
                $defaultValues[$filter->getName()] = $filter->getDefaultValue();
            }
        }

        $data = array_merge($defaultValues, $data);
        $form = $this->formFactory->createNamedBuilder($table->getId().'_form', FormType::class, $data);
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
        $table->setForm($form->getForm());

        return $table->getForm()->createView();
    }

    /**
     * {@inheritdoc}
     */
    public function createFormView(TableInterface $table, array $data = array())
    {
        return $table->setFormView($this->form($table, $data));
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
        $stream = fopen('php://memory', 'w+');
        // execute query with filters, without pagination, only scalar results
        $rows = $this->getRows($table, $request, false, false);
        // first line: keys
        if (count($rows) > 0) {
            $headers = array_map(function($column){
                return $column->getExportName() ?? $column->getName();
            }, $table->getColumns());

            if ($table->haveTotalColumns()) {
                $headers = array_merge([""], $headers);
            }

            fputcsv($stream, $headers, ';', '"', '\\');
        }

        foreach ($rows as $row) {
            $line = array_map(function($column) use ($row, $rows){
                return $column->getExportValue($row, $rows);
            }, $table->getColumns());

            if ($table->haveTotalColumns()){
                $line = array_merge([""], $line);
            }

            fputcsv($stream, $line, ';', '"', '\\');
        }


        if ($table->haveTotalColumns()) {
            $total = array_map(function($column){
                return $column->getTotal();
            }, $table->getColumns());

            fputcsv($stream, array_merge(['Total'], $total), ';', '"', '\\');
        }

        rewind($stream);
        $buffer = stream_get_contents($stream);
        fclose($stream);
        return $buffer;
    }

    /**
     * Handle the user request and return the JSON response (with pagination).
     *
     * @param TableInterface $table
     * @param Request        $request
     *
     * @return Response
     * @throws \Exception|\Throwable
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

        $template = $this->twig->load($table->getTemplate());

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
            'tableFoot' => $template->renderBlock(
                'tableFoot',
                array_merge($twigParams, array('tableRenderFoot' => true))
            ),
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
        $identifiers = $request->request->all($table->getSelectionFormKey());
        $entities = $this->loadRowsById($table, $identifiers);

        return $entities;
    }
}
