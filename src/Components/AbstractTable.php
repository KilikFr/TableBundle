<?php

namespace Kilik\TableBundle\Components;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

abstract class AbstractTable implements TableInterface
{
    /**
     * Table id.
     */
    protected string $id;

    protected string $title;

    /**
     * URL for ajax call.
     */
    protected string $path;

    /**
     * Filters applied on the table.
     *
     * @var Filter[]
     */
    protected array $filters;

    /**
     * Rows per page.
     */
    protected int $rowsPerPage = 10;

    /**
     * Rows per page (options).
     *
     * @var array|int
     */
    protected $rowsPerPageOptions = [5, 10, 20, 50, 100];

    /**
     * Template for table and lines.
     */
    private string $template = '@KilikTable/_defaultTable.html.twig';

    /**
     * Params to pass to twig (when rendering the template).
     */
    private array $templateParams = [];

    private int $page;

    private int $lastPage;

    private int $totalRows;

    private int $filteredRows;

    /**
     * @var FormInterface|null
     */
    private $form;

    private FormView $formView;

    /**
     * @var array|Column
     */
    private $columns;

    /**
     * custom options.
     */
    private array $customOptions = [];

    /**
     * @var MassAction[]
     */
    private array $massActions = [];

    /**
     * Skip load from local storage.
     */
    private bool $skipLoadFromLocalStorage = false;

    /**
     * Skip load form filters data from local storage.
     */
    private bool $skipLoadFilterFromLocalStorage = false;

    /**
     * Table constructor.
     */
    public function __construct()
    {
        $this->filters = [];
        $this->columns = [];
    }

    /**
     * Set table identifiant.
     *
     * @return static
     */
    public function setId(string $id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set table title.
     *
     * @return static
     */
    public function setTitle(string $title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set URL for ajax call.
     *
     * @return static
     */
    public function setPath(string $path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @return static
     */
    public function setTemplate(string $template)
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * Set template params.
     *
     * @return static
     */
    public function setTemplateParams(array $templateParams)
    {
        $this->templateParams = $templateParams;

        return $this;
    }

    /**
     * Get template params.
     */
    public function getTemplateParams(): array
    {
        return $this->templateParams;
    }

    /**
     * Get Table ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get Table Title.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get Table path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Set Rows per page.
     *
     * @return static
     */
    public function setRowsPerPage(int $rowsPerPage)
    {
        $this->rowsPerPage = $rowsPerPage;

        return $this;
    }

    /**
     * Get rows per page.
     */
    public function getRowsPerPage(): int
    {
        return $this->rowsPerPage;
    }

    /**
     * Set rows per page options (selectable).
     *
     * @param array|int $rowsPerPageOptions
     *
     * @return static
     */
    public function setRowsPerPageOptions($rowsPerPageOptions)
    {
        $this->rowsPerPageOptions = $rowsPerPageOptions;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getRowsPerPageOptions()
    {
        return $this->rowsPerPageOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function setPage($page)
    {
        $this->page = max(1, $page);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * {@inheritdoc}
     */
    public function getPreviousPage(): int
    {
        return $this->page - 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getNextPage(): int
    {
        return min($this->lastPage, $this->page + 1);
    }

    /**
     * {@inheritdoc}
     */
    public function setLastPage($page)
    {
        $this->lastPage = $page;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * {@inheritdoc}
     */
    public function setTotalRows($totalRows)
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    /**
     * {@inheritdoc}
     */
    public function setFilteredRows($filteredRows)
    {
        $this->filteredRows = $filteredRows;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilteredRows(): int
    {
        return $this->filteredRows;
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter(Filter $filter)
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllFilters()
    {
        $filters = $this->getFilters();
        foreach ($this->getColumns() as $column) {
            if (!is_null($column->getFilter())) {
                $filters[] = $column->getFilter();
            }
        }

        return $filters;
    }

    /**
     * {@inheritdoc}
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * {@inheritdoc}
     */
    public function setForm(FormInterface $form)
    {
        $this->form = $form;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setFormView(FormView $formView)
    {
        $this->formView = $formView;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormView()
    {
        return $this->formView;
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn(Column $column)
    {
        $this->columns[] = $column;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnByName($name)
    {
        foreach ($this->columns as $column) {
            // if name match
            if ($column->getName() == $name) {
                return $column;
            }
        }

        // if not found
        return;
    }

    /**
     * {@inheritdoc}
     */
    public function getBodyId(): string
    {
        return $this->id.'_body';
    }

    /**
     * {@inheritdoc}
     */
    public function getFootId(): string
    {
        return $this->id.'_foot';
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId(): string
    {
        return $this->id.'_form';
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstRow(): int
    {
        return ($this->page - 1) * $this->rowsPerPage + 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastRow(): int
    {
        return min($this->filteredRows, ($this->page) * $this->rowsPerPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(Column $column, array $row, array $rows = [])
    {
        if (!is_null($column->getName())) {
            return $column->getValue($row, $rows);
        }

        return;
    }

    /**
     * {@inheritdoc}
     */
    public function addCustomOption($option, $value)
    {
        $this->customOptions[$option] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function getHiddenColumnsNames(): array
    {
        $hiddenColumns = [];

        foreach ($this->columns as $column) {
            if ($column->getHiddenByDefault()) {
                $hiddenColumns[] = $column->getName();
            }
        }

        return $hiddenColumns;
    }

    /**
     * {@inheritdoc}
     */
    public function setSkipLoadFromLocalStorage(bool $skipLoadFromLocalStorage)
    {
        $this->skipLoadFromLocalStorage = $skipLoadFromLocalStorage;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isSkipLoadFromLocalStorage(): bool
    {
        return $this->skipLoadFromLocalStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function setSkipLoadFilterFromLocalStorage(bool $skip)
    {
        $this->skipLoadFilterFromLocalStorage = $skip;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isSkipLoadFilterFromLocalStorage(): bool
    {
        return $this->skipLoadFilterFromLocalStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return array_merge(
            $this->customOptions,
            [
                'rowsPerPage' => $this->rowsPerPage,
                'defaultHiddenColumns' => $this->getHiddenColumnsNames(),
                'skipLoadFromLocalStorage' => $this->skipLoadFromLocalStorage,
                'skipLoadFilterFromLocalStorage' => $this->skipLoadFilterFromLocalStorage,
             ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilterByName(string $filterName)
    {
        foreach ($this->getAllFilters() as $filter) {
            if ($filter->getName() == $filterName) {
                return $filter;
            }
        }

        return;
    }

    /**
     * @param MassAction $massAction
     *
     * @return static
     */
    public function addMassAction(MassAction $massAction)
    {
        $this->massActions[] = $massAction;

        return $this;
    }

    /**
     * @return MassAction[]
     */
    public function getMassActions()
    {
        return $this->massActions;
    }

    public function getSelectionFormKey(): string
    {
        return 'kilik_' . $this->getId() . '_selected';
    }
}
