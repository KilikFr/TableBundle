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
    private $template;

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
     * Constructeur
     */
    public function __construct()
    {
        $this->filters = [];
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

}
