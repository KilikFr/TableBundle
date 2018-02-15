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
     *
     * @return FormView
     */
    public function form(TableInterface $table);

    /**
     * Build filter form and get twig params for main view.
     *
     * @param TableInterface $table
     *
     * @return Table
     */
    public function createFormView(TableInterface $table);

    /**
     * Handle the user request and return an array of all elements.
     *
     * @param TableInterface $table
     * @param Request        $request
     * @param bool           $paginate   : limit selections with pagination mecanism
     * @param bool           $getObjects : get objects (else, only scalar results)
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
