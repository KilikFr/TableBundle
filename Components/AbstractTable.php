<?php

namespace Kilik\TableBundle\Components;

abstract class AbstractTable implements TableInterface
{
    /**
     * Table id.
     *
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $title;

    /**
     * URL for ajax call.
     *
     * @var string
     */
    protected $path;

    /**
     * Filters applied on the table.
     *
     * @var Filter[]
     */
    protected $filters;

    /**
     * Rows per page.
     *
     * @var int
     */
    protected $rowsPerPage = 10;

    /**
     * Rows per page (options).
     *
     * @var array|int
     */
    protected $rowsPerPageOptions = [5, 10, 20, 50, 100];

    /**
     * Template for table and lines.
     *
     * @var string
     */
    private $template = 'KilikTableBundle::_defaultTable.html.twig';

    /**
     * Params to pass to twig (when rendering the template).
     *
     * @var array
     */
    private $templateParams = [];

    /**
     * @var int
     */
    private $page;

    /**
     * @var int
     */
    private $lastPage;

    /**
     * @var int
     */
    private $totalRows;

    /**
     * @var int
     */
    private $filteredRows;

    /**
     * @var string
     */
    private $formView;

    /**
     * @var array|Column
     */
    private $columns;

    /**
     * custom options.
     *
     * @var array
     */
    private $customOptions = [];

    /**
     * @var MassAction[]
     */
    private $massActions = [];

    /**
     * Skip load from local storage.
     *
     * @var bool
     */
    private $skipLoadFromLocalStorage = false;

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
     * @param string $id
     *
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set table title.
     *
     * @param string $id
     *
     * @return static
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set URL for ajax call.
     *
     * @param string $path
     *
     * @return static
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @param string $template
     *
     * @return static
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Set template params.
     *
     * @param array $templateParams
     *
     * @return static
     */
    public function setTemplateParams($templateParams)
    {
        $this->templateParams = $templateParams;

        return $this;
    }

    /**
     * Get template params.
     *
     * @return array
     */
    public function getTemplateParams()
    {
        return $this->templateParams;
    }

    /**
     * Get Table ID.
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get Table Title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Get Table path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set Rows per page.
     *
     * @param int $rowsPerPage
     *
     * @return static
     */
    public function setRowsPerPage($rowsPerPage)
    {
        $this->rowsPerPage = $rowsPerPage;

        return $this;
    }

    /**
     * Get rows per page.
     *
     * @return int
     */
    public function getRowsPerPage()
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
    public function getPage()
    {
        return $this->page;
    }

    /**
     * {@inheritdoc}
     */
    public function getPreviousPage()
    {
        return $this->page - 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getNextPage()
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
    public function getLastPage()
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
    public function getTotalRows()
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
    public function getFilteredRows()
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
    public function setFormView($formView)
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
    public function getBodyId()
    {
        return $this->id.'_body';
    }

    /**
     * {@inheritdoc}
     */
    public function getFootId()
    {
        return $this->id.'_foot';
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return $this->id.'_form';
    }

    /**
     * {@inheritdoc}
     */
    public function getFirstRow()
    {
        return ($this->page - 1) * $this->rowsPerPage + 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastRow()
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
    public function getCustomOptions()
    {
        return $this->customOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function getHiddenColumnsNames()
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
    public function setSkipLoadFromLocalStorage($skipLoadFromLocalStorage)
    {
        $this->skipLoadFromLocalStorage = $skipLoadFromLocalStorage;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isSkipLoadFromLocalStorage()
    {
        return $this->skipLoadFromLocalStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return array_merge(
            $this->customOptions,
            [
                'rowsPerPage' => $this->rowsPerPage,
                'defaultHiddenColumns' => $this->getHiddenColumnsNames(),
                'skipLoadFromLocalStorage' => $this->skipLoadFromLocalStorage,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilterByName($filterName)
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

    /**
     * @return string
     */
    public function getSelectionFormKey()
    {
        return 'kilik_' . $this->getId() . '_selected';
    }
}
