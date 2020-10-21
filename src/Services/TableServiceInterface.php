<?php

namespace Kilik\TableBundle\Services;

use Kilik\TableBundle\Components\TableInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Kilik\TableBundle\Components\Table;
use Kilik\TableBundle\Components\Filter;

interface TableServiceInterface
{
    /**
     * Get the form (for filtering).
     *
     * @param TableInterface $table
     * @param array $data Initialize table form filters data
     *
     * @return FormView
     */
    public function form(TableInterface $table, array $data = array());

    /**
     * Build filter form and get twig params for main view.
     *
     * @param TableInterface $table
     * @param array $data Initialize table form filters data
     *
     * @return Table
     */
    public function createFormView(TableInterface $table, array $data = array());

    /**
     * Handle the user request and return an array of all elements.
     *
     * @param TableInterface $table
     * @param Request        $request
     * @param bool           $paginate   : limit selections with pagination mecanism
     * @param bool           $getObjects : get objects (else, only scalar results)
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
     * @param TableInterface $table
     * @param Request        $request
     *
     * @return string
     */
    public function exportAsCsv(TableInterface $table, Request $request);

    /**
     * Handle the user request and return the JSON response (with pagination).
     *
     * @param TableInterface $table
     * @param Request        $request
     *
     * @return Response
     */
    public function handleRequest(TableInterface $table, Request $request);


    /**
     * @param TableInterface $table
     * @param                $identifiers
     * @return mixed
     */
    public function loadRowsById(TableInterface $table, $identifiers);
}
