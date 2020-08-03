<?php

namespace Kilik\TableBundle\Api;

interface ResultInterface
{
    /**
     * Nb Total Rows.
     *
     * @return int
     */
    public function getNbTotalRows();

    /**
     * Nb Filtered Rows.
     *
     * @return int
     */
    public function getNbFilteredRows();

    /**
     * Rows.
     *
     * @return array
     */
    public function getRows();
}
