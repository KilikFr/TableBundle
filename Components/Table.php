<?php

namespace Kilik\TableBundle\Components;

use Doctrine\ORM\QueryBuilder;

class Table
{
    const ENTITY_LOADER_NONE = 0;
    // old entity loader mechanism
    const ENTITY_LOADER_LEGACY = 1;
    // entity loader from Repository Name
    const ENTITY_LOADER_REPOSITORY = 2;
    // entity loader from custom load method
    const ENTITY_LOADER_CALLBACK = 3;

    /**
     * Table id.
     *
     * @var string
     */
    private $id;

    /**
     * URL for ajax call.
     *
     * @var string
     */
    private $path;

    /**
     * Filters applied on the table.
     *
     * @var array|Filter
     */
    private $filters;

    /**
     * Rows per page.
     *
     * @var int
     */
    private $rowsPerPage = 10;

    /**
     * Rows per page (options).
     *
     * @var array|int
     */
    private $rowsPerPageOptions = [5, 10, 20, 50, 100];

    /**
     * @var QueryBuilder
     */
    private $queryBuilder;

    /**
     * Root entity alias.
     *
     * @var string
     */
    private $alias;

    /**
     * Identifier fields used to run count queries.
     * If is null a classical 'COUNT(*) FROM (query)' will be done.
     * Be aware no to use this option with GROUP BY query.
     *
     * @var string|void
     */
    private $identifierFieldNames = null;

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
     * Skip load from local storage.
     *
     * @var bool
     */
    private $skipLoadFromLocalStorage = false;

    /**
     * Entity loader method.
     *
     * @var string
     */
    private $entityLoaderMode = self::ENTITY_LOADER_LEGACY;

    /**
     * Entity loader repository name (ENTITY_LOADER_REPOSITORY mode).
     *
     * @var string
     */
    private $entityLoaderRepository = null;

    /**
     * Entity loader callback (ENTITY_LOADER_METHOD mode).
     *
     * @var callable
     */
    private $entityLoaderCallback = null;

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
     * @param QueryBuilder $queryBuilder
     * @param string       $alias
     *
     * @return static
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder, $alias)
    {
        $this->queryBuilder = $queryBuilder;
        $this->alias = $alias;

        return $this;
    }

    /**
     * Defines default identifiers from query builder in order to optimize count queries.
     *
     * @return $this
     *
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
     */
    public function setDefaultIdentifierFieldNames()
    {
        //Default identifier for table rows
        $rootEntity = $this->queryBuilder->getRootEntities()[0];
        $metadata = $this->queryBuilder->getEntityManager()->getMetadataFactory()->getMetadataFor($rootEntity);
        $identifiers = array();
        foreach ($metadata->getIdentifierFieldNames() as $identifierFieldName) {
            $identifiers[] = $this->getAlias().'.'.$identifierFieldName;
        }
        $rootEntityIdentifier = implode(',', $identifiers);
        $this->setIdentifierFieldNames($rootEntityIdentifier ?: null);

        return $this;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @param string|null $identifierFieldNames
     *
     * @return static
     */
    public function setIdentifierFieldNames($identifierFieldNames = null)
    {
        $this->identifierFieldNames = $identifierFieldNames;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getIdentifierFieldNames()
    {
        return $this->identifierFieldNames;
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
     * Get rows per page options (selectable).
     *
     * @return int
     */
    public function getRowsPerPageOptions()
    {
        return $this->rowsPerPageOptions;
    }

    /**
     * @param int $page
     *
     * @return static
     */
    public function setPage($page)
    {
        $this->page = max(1, $page);

        return $this;
    }

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @return int
     */
    public function getPreviousPage()
    {
        return $this->page - 1;
    }

    /**
     * @return int
     */
    public function getNextPage()
    {
        return min($this->lastPage, $this->page + 1);
    }

    /**
     * @param int $page
     *
     * @return static
     */
    public function setLastPage($page)
    {
        $this->lastPage = $page;

        return $this;
    }

    /**
     * @return int
     */
    public function getLastPage()
    {
        return $this->lastPage;
    }

    /**
     * @param int $totalRows
     *
     * @return static
     */
    public function setTotalRows($totalRows)
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    /**
     * @return int
     */
    public function getTotalRows()
    {
        return $this->totalRows;
    }

    /**
     * @param int $filteredRows
     *
     * @return static
     */
    public function setFilteredRows($filteredRows)
    {
        $this->filteredRows = $filteredRows;

        return $this;
    }

    /**
     * @return int
     */
    public function getFilteredRows()
    {
        return $this->filteredRows;
    }

    /**
     * @param Filter $filter
     *
     * @return $this
     */
    public function addFilter(Filter $filter)
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * @return Filter[]
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Get all filters (filters + column filters).
     *
     * @return Filter[]
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
     * @param string $formView
     *
     * @return static
     */
    public function setFormView($formView)
    {
        $this->formView = $formView;

        return $this;
    }

    /**
     * @return string
     */
    public function getFormView()
    {
        return $this->formView;
    }

    /**
     * @param Column $column
     *
     * @return $this
     */
    public function addColumn(Column $column)
    {
        $this->columns[] = $column;

        return $this;
    }

    /**
     * @return Column[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get a column by its name.
     *
     * @param string $name
     *
     * @return Column|void
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
     * Get the table body id.
     *
     * @return string
     */
    public function getBodyId()
    {
        return $this->id.'_body';
    }

    /**
     * Get the table foot id.
     *
     * @return string
     */
    public function getFootId()
    {
        return $this->id.'_foot';
    }

    /**
     * Get the form id.
     *
     * @return string
     */
    public function getFormId()
    {
        return $this->id.'_form';
    }

    /**
     * Get the first row rank.
     *
     * @return int
     */
    public function getFirstRow()
    {
        return ($this->page - 1) * $this->rowsPerPage + 1;
    }

    /**
     * Get the last row rank.
     *
     * @return int
     */
    public function getLastRow()
    {
        return min($this->filteredRows, ($this->page) * $this->rowsPerPage);
    }

    /**
     * Get the formatted value to display.
     *
     * @param Column $column
     * @param array  $row
     * @param array  $rows
     *
     * @return string|void
     */
    public function getValue(Column $column, array $row, array $rows = [])
    {
        if (!is_null($column->getName())) {
            return $column->getValue($row, $rows);
        }

        return;
    }

    /**
     * Add a custom option.
     *
     * @param string $option
     * @param mixed  $value
     *
     * @return static
     */
    public function addCustomOption($option, $value)
    {
        $this->customOptions[$option] = $value;

        return $this;
    }

    /**
     * Get custom options.
     *
     * @return array
     */
    public function getCustomOptions()
    {
        return $this->customOptions;
    }

    /**
     * Get hidden columns names.
     *
     * @return array
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
     * @param bool $skipLoadFromLocalStorage
     *
     * @return static
     */
    public function setSkipLoadFromLocalStorage($skipLoadFromLocalStorage)
    {
        $this->skipLoadFromLocalStorage = $skipLoadFromLocalStorage;

        return $this;
    }

    /**
     * @return bool
     */
    public function isSkipLoadFromLocalStorage()
    {
        return $this->skipLoadFromLocalStorage;
    }

    /**
     * Get table options (for javascript).
     *
     * @return array
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
     * @param int $entityLoaderMode
     *
     * @return static
     */
    public function setEntityLoaderMode($entityLoaderMode)
    {
        $this->entityLoaderMode = $entityLoaderMode;

        return $this;
    }

    /**
     * @return int
     */
    public function getEntityLoaderMode()
    {
        return $this->entityLoaderMode;
    }

    /**
     * @param string $entityLoaderRepository
     *
     * @return static
     */
    public function setEntityLoaderRepository($entityLoaderRepository)
    {
        // force mode
        $this->setEntityLoaderMode(self::ENTITY_LOADER_REPOSITORY);

        $this->entityLoaderRepository = $entityLoaderRepository;

        return $this;
    }

    /**
     * @return string
     */
    public function getEntityLoaderRepository()
    {
        return $this->entityLoaderRepository;
    }

    /**
     * @param callable $entityLoaderCallback
     *
     * @return static
     */
    public function setEntityLoaderCallback($entityLoaderCallback)
    {
        // force mode
        $this->setEntityLoaderMode(self::ENTITY_LOADER_CALLBACK);

        $this->entityLoaderCallback = $entityLoaderCallback;

        return $this;
    }

    /**
     * @return callable
     */
    public function getEntityLoaderCallback()
    {
        return $this->entityLoaderCallback;
    }
}
