<?php

namespace Kilik\TableBundle\Components;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormBuilder;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Twig_Environment;

class Table
{

    /**
     * Identifiant de la table
     * 
     * @var string 
     */
    private $id;

    /**
     * Chemin pour les appels ajax
     * 
     * @var string 
     */
    private $path;

    /**
     * Filtres applicables sur la table
     * 
     * @var array|Filter
     */
    private $filters;

    /**
     * Enregistrements par page
     * 
     * @var int
     */
    private $rowsPerPage = 10;

    /**
     * Générateur de requêtes
     * 
     * @var QueryBuilder 
     */
    private $queryBuilder;

    /**
     * Alias de l'entité principale
     * 
     * @var type 
     */
    private $alias;

    /**
     * Template pour la génération des lignes
     * 
     * @var type 
     */
    private $template = "KilikTableBundle::_defaultTable.html.twig";

    /**
     * 
     * 
     * @var int
     */
    private $page;

    /**
     * 
     * 
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
     * @var type
     */
    private $formView;

    /**
     * @var array|Column
     */
    private $columns;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->filters = [];
        $this->columns = [];
    }

    /**
     * Définir l'identifiant de la table
     * 
     * @param type $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Définir le chemin pour les appels ajax
     * 
     * @param type $path
     * @return static
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * 
     * @param QueryBuilder $queryBuilder
     * @param string $alias
     * @return static
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder, $alias)
    {
        $this->queryBuilder = $queryBuilder;
        $this->alias        = $alias;

        return $this;
    }

    /**
     * 
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * 
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * 
     * @param string $template
     * @return static
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * 
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * 
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * 
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * 
     * @param int $rowsPerPage
     * @return static
     */
    public function setRowsPerPage($rowsPerPage)
    {
        $this->rowsPerPage = $rowsPerPage;

        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getRowsPerPage()
    {
        return $this->rowsPerPage;
    }

    /**
     * 
     * @param int $page
     * @return static
     */
    public function setPage($page)
    {
        $this->page = max(1, $page);

        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * 
     * @return int
     */
    public function getPreviousPage()
    {
        return max(1, $this->page - 1);
    }

    /**
     * 
     * @return int
     */
    public function getNextPage()
    {
        return min($this->lastPage, $this->page + 1);
    }

    /**
     * 
     * @param int $page
     * @return static
     */
    public function setLastPage($page)
    {
        $this->lastPage = $page;

        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getLastPage()
    {
        return $this->lastPage;
    }

    /**
     * 
     * @param int $totalRows
     * @return static
     */
    public function setTotalRows($totalRows)
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getTotalRows()
    {
        return $this->totalRows;
    }

    /**
     * 
     * @param int $filteredRows
     * @return static
     */
    public function setFilteredRows($filteredRows)
    {
        $this->filteredRows = $filteredRows;

        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getFilteredRows()
    {
        return $this->filteredRows;
    }

    /**
     * Ajouter un filtre
     * 
     * @param Filter $filter
     */
    public function addFilter(Filter $filter)
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * @return array|Filter
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Get all filters (filters + column filters)
     * 
     * @return array|Filter
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
     * Formulaire
     * 
     * @param type $formView
     */
    public function setFormView($formView)
    {
        $this->formView = $formView;

        return $this;
    }

    /**
     * Formulaire
     * 
     * @return
     */
    public function getFormView()
    {
        return $this->formView;
    }

    /**
     * Ajouter une colonne
     * 
     * @param Column $column
     */
    public function addColumn(Column $column)
    {
        $this->columns[] = $column;

        return $this;
    }

    /**
     * @return array|Column
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get a column by its name
     * 
     * @param string $name
     * @return Column
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
        return null;
    }

    /**
     * Get the table body id
     * 
     * @return string
     */
    public function getBodyId()
    {
        return $this->id."_body";
    }

    /**
     * Get the table foot id
     * 
     * @return string
     */
    public function getFootId()
    {
        return $this->id."_foot";
    }

    /**
     * Get the form id
     * 
     * @return string
     */
    public function getFormId()
    {
        return $this->id."_form";
    }

    /**
     * Get the first row rank
     * 
     * @return int
     */
    public function getFirstRow()
    {
        return ($this->page - 1) * $this->rowsPerPage + 1;
    }

    /**
     * Get the last row rank
     * 
     * @return int
     */
    public function getLastRow()
    {
        return min($this->filteredRows, ($this->page) * $this->rowsPerPage);
    }

}
