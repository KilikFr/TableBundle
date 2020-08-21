<?php

namespace Kilik\TableBundle\Components;

interface TableInterface
{
    /**
     * Set table identifier.
     *
     * @param string $id
     *
     * @return static
     */
    public function setId($id);

    /**
     * Set URL for ajax call.
     *
     * @param string $path
     *
     * @return static
     */
    public function setPath($path);

    /**
     * @param string $template
     *
     * @return static
     */
    public function setTemplate($template);

    /**
     * @return string
     */
    public function getTemplate();

    /**
     * Set template params.
     *
     * @param array $templateParams
     *
     * @return static
     */
    public function setTemplateParams($templateParams);

    /**
     * Get template params.
     *
     * @return array
     */
    public function getTemplateParams();

    /**
     * Get Table ID.
     *
     * @return string
     */
    public function getId();

    /**
     * Get Table path.
     *
     * @return string
     */
    public function getPath();

    /**
     * Set Rows per page.
     *
     * @param int $rowsPerPage
     *
     * @return static
     */
    public function setRowsPerPage($rowsPerPage);

    /**
     * Get rows per page.
     *
     * @return int
     */
    public function getRowsPerPage();

    /**
     * Set rows per page options (selectable).
     *
     * @param array|int $rowsPerPageOptions
     *
     * @return static
     */
    public function setRowsPerPageOptions($rowsPerPageOptions);

    /**
     * Get rows per page options (selectable).
     *
     * @return int
     */
    public function getRowsPerPageOptions();

    /**
     * @param int $page
     *
     * @return static
     */
    public function setPage($page);

    /**
     * @return int
     */
    public function getPage();

    /**
     * @return int
     */
    public function getPreviousPage();

    /**
     * @return int
     */
    public function getNextPage();

    /**
     * @param int $page
     *
     * @return static
     */
    public function setLastPage($page);

    /**
     * @return int
     */
    public function getLastPage();

    /**
     * @param int $totalRows
     *
     * @return static
     */
    public function setTotalRows($totalRows);

    /**
     * @return int
     */
    public function getTotalRows();

    /**
     * @param int $filteredRows
     *
     * @return static
     */
    public function setFilteredRows($filteredRows);

    /**
     * @return int
     */
    public function getFilteredRows();

    /**
     * @param Filter $filter
     *
     * @return $this
     */
    public function addFilter(Filter $filter);

    /**
     * @return Filter[]
     */
    public function getFilters();

    /**
     * Get all filters (filters + column filters).
     *
     * @return Filter[]
     */
    public function getAllFilters();

    /**
     * @param string $formView
     *
     * @return static
     */
    public function setFormView($formView);

    /**
     * @return string
     */
    public function getFormView();

    /**
     * @param Column $column
     *
     * @return $this
     */
    public function addColumn(Column $column);

    /**
     * @return Column[]
     */
    public function getColumns();

    /**
     * Get a column by its name.
     *
     * @param string $name
     *
     * @return Column|void
     */
    public function getColumnByName($name);

    /**
     * Get the table body id.
     *
     * @return string
     */
    public function getBodyId();

    /**
     * Get the table foot id.
     *
     * @return string
     */
    public function getFootId();

    /**
     * Get the form id.
     *
     * @return string
     */
    public function getFormId();

    /**
     * Get the first row rank.
     *
     * @return int
     */
    public function getFirstRow();

    /**
     * Get the last row rank.
     *
     * @return int
     */
    public function getLastRow();

    /**
     * Get the formatted value to display.
     *
     * @param Column $column
     * @param array  $row
     * @param array  $rows
     *
     * @return string|void
     */
    public function getValue(Column $column, array $row, array $rows = []);

    /**
     * Add a custom option.
     *
     * @param string $option
     * @param mixed  $value
     *
     * @return static
     */
    public function addCustomOption($option, $value);

    /**
     * Get custom options.
     *
     * @return array
     */
    public function getCustomOptions();

    /**
     * Get hidden columns names.
     *
     * @return array
     */
    public function getHiddenColumnsNames();

    /**
     * @param bool $skipLoadFromLocalStorage
     *
     * @return static
     */
    public function setSkipLoadFromLocalStorage($skipLoadFromLocalStorage);

    /**
     * @return bool
     */
    public function isSkipLoadFromLocalStorage();

    /**
     * Get table options (for javascript).
     *
     * @return array
     */
    public function getOptions();

    /**
     * Get filter by name
     *
     * @param string $filterName
     *
     * @return Filter
     */
    public function getFilterByName($filterName);

    /**
     * Get form key of row selection
     *
     * @return string
     */
    public function getSelectionFormKey();
}
