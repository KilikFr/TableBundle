<?php

namespace Kilik\TableBundle\Components;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

interface TableInterface
{
    /**
     * Set table identifier.
     *
     * @return static
     */
    public function setId(string $id);

    /**
     * Set URL for ajax call.
     *
     * @return static
     */
    public function setPath(string $path);

    /**
     * @return static
     */
    public function setTemplate(string $template);

    public function getTemplate(): string;

    /**
     * Set template params.
     * @return static
     */
    public function setTemplateParams(array $templateParams);

    /**
     * Get template params.
     */
    public function getTemplateParams(): array;

    /**
     * Get Table ID.
     */
    public function getId(): string;

    /**
     * Get Table path.
     */
    public function getPath(): string;

    /**
     * Set Rows per page.
     *
     * @return static
     */
    public function setRowsPerPage(int $rowsPerPage);

    /**
     * Get rows per page.
     */
    public function getRowsPerPage(): int;

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
     * @return array|int
     */
    public function getRowsPerPageOptions();

    /**
     * @return static
     */
    public function setPage(int $page);

    public function getPage(): int;

    public function getPreviousPage(): int;

    public function getNextPage(): int;

    public function setLastPage(int $page);

    public function getLastPage(): int;

    public function setTotalRows(int $totalRows);

    public function getTotalRows(): int;

    /**
     * @return static
     */
    public function setFilteredRows(int $filteredRows);

    public function getFilteredRows(): int;

    /**
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
     * @internal see \Kilik\TableBundle\Services\AbstractTableService::form()
     *
     * @return static
     */
    public function setForm(FormInterface $form);

    /**
     * @return FormInterface|null
     */
    public function getForm();

    /**
     * @return static
     */
    public function setFormView(FormView $formView);

    /**
     * @return FormView|null
     */
    public function getFormView();

    /**
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
     * @return Column|void
     */
    public function getColumnByName(string $name);

    /**
     * Get the table body id.
     */
    public function getBodyId(): string;

    /**
     * Get the table foot id.
     */
    public function getFootId(): string;

    /**
     * Get the form id.
     */
    public function getFormId(): string;

    /**
     * Get the first row rank.
     */
    public function getFirstRow(): int;

    /**
     * Get the last row rank.
     */
    public function getLastRow(): int;

    /**
     * Get the formatted value to display.
     *
     * @return string|void
     */
    public function getValue(Column $column, array $row, array $rows = []);

    /**
     * Add a custom option.
     *
     * @param mixed  $value
     *
     * @return static
     */
    public function addCustomOption(string $option, $value);

    /**
     * Get custom options.
     */
    public function getCustomOptions(): array;

    /**
     * Get hidden columns names.
     */
    public function getHiddenColumnsNames(): array;

    /**
     * @return static
     */
    public function setSkipLoadFromLocalStorage(bool $skipLoadFromLocalStorage);

    public function isSkipLoadFromLocalStorage(): bool;

    /**
     * @return static
     */
    public function setSkipLoadFilterFromLocalStorage(bool $skip);

    public function isSkipLoadFilterFromLocalStorage(): bool;

    /**
     * Get table options (for javascript).
     */
    public function getOptions(): array;

    /**
     * Get filter by name
     *
     * @return Filter
     */
    public function getFilterByName(string $filterName);

    /**
     * Enable or disable responsive mode (card layout on mobile).
     *
     * @return static
     */
    public function setResponsive(bool $responsive);

    /**
     * Whether responsive mode is enabled.
     */
    public function isResponsive(): bool;

    /**
     * Get form key of row selection
     */
    public function getSelectionFormKey(): string;
}
