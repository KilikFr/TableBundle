<?php

namespace Kilik\TableBundle\Api;

abstract class AbstractResult implements ResultInterface
{
    /**
     * Total rows.
     *
     * @var int
     */
    protected $nbTotalRows;

    /**
     * Total Filtered rows.
     *
     * @var int
     */
    protected $nbFilteredRows;

    /**
     * Rows.
     *
     * @var array
     */
    protected $rows;

    /**
     * Set Nb Total Rows.
     *
     * @param int
     *
     * @return static
     */
    public function setNbTotalRows($nbTotalRows)
    {
        $this->nbTotalRows = $nbTotalRows;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNbTotalRows()
    {
        return $this->nbTotalRows;
    }

    /**
     * Set Nb Filtered Rows.
     *
     * @param int
     *
     * @return static
     */
    public function setNbFilteredRows($nbFilteredRows)
    {
        $this->nbFilteredRows = $nbFilteredRows;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNbFilteredRows()
    {
        return $this->nbFilteredRows;
    }

    /**
     * Add a row.
     *
     * @param mixed $row
     */
    public function addRow($row)
    {
        $this->rows[] = $row;
    }

    /**
     * {@inheritdoc}
     */
    public function getRows()
    {
        return $this->rows;
    }
}
