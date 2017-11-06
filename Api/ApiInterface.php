<?php

namespace Kilik\TableBundle\Api;

use Kilik\TableBundle\Components\TableInterface;

interface ApiInterface
{
    /**
     * Load results.
     *
     * @param TableInterface $table
     * @param array          $filters associative array (key=>value)
     * @param array          $orderBy associative aray (ex: name=>ASC,email=>DESC)
     * @param int            $page
     * @param int            $limit
     *
     * @return ResultInterface
     */
    public function load(TableInterface $table, $filters, $orderBy = [], $page = null, $limit = null);
}
