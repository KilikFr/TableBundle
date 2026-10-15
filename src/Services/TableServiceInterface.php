<?php

namespace Kilik\TableBundle\Services;

use Kilik\TableBundle\Components\Filter;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\TableInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface TableServiceInterface
{
    /**
     * Get the form (for filtering).
     *
     * @param array $data Initialize table form filters data
     *
     * @return FormView
     */
    public function form(TableInterface $table, array $data = []);

    /**
     * Build filter form and get twig params for main view.
     *
     * @param array $data Initialize table form filters data
     *
     * @return TableInterface the table given as argument
     */
    public function createFormView(TableInterface $table, array $data = []);

    /**
     * Handle the user request and return an array of all elements.
     *
     * @param bool $paginate   : limit selections with pagination mecanism
     * @param bool $getObjects : get objects (else, only scalar results)
     *
     * @todo handle request with symfony form instead of manually parsing GET parameters
     *
     * @return array
     *
     * table attributes are modified (if paginate=true)
     */
    public function getRows(TableInterface $table, Request $request, $paginate = true, $getObjects = true);

    /**
     * Export (selection by filters) as a CSV buffer.
     *
     * @return string
     */
    public function exportAsCsv(TableInterface $table, Request $request);

    /**
     * Handle the user request and return the JSON response (with pagination).
     *
     * @return Response
     */
    public function handleRequest(TableInterface $table, Request $request);

    public function loadRowsById(TableInterface $table, $identifiers);
}
