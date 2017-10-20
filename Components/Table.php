<?php

namespace Kilik\TableBundle\Components;

use Doctrine\ORM\QueryBuilder;

class Table extends AbstractTable
{
    const ENTITY_LOADER_NONE = 0;
    // old entity loader mechanism
    const ENTITY_LOADER_LEGACY = 1;
    // entity loader from Repository Name
    const ENTITY_LOADER_REPOSITORY = 2;
    // entity loader from custom load method
    const ENTITY_LOADER_CALLBACK = 3;

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
