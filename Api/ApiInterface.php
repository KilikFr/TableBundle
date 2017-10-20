<?php

namespace Kilik\TableBundle\Api;

interface ApiInterface
{
    /**
     * Load results.
     *
     * @param array $filters associative array (key=>value)
     * @param array $orderBy associative aray (ex: name=>ASC,email=>DESC)
     * @param int   $offset
     * @param int   $limit
     *
     * @return ResultInterface
     */
    public function load($filters, $orderBy = [], $offset = null, $limit = null);
}
