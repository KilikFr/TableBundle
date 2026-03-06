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

    private QueryBuilder $queryBuilder;

    /**
     * Root entity alias.
     */
    private string $alias;

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
     */
    private int $entityLoaderMode = self::ENTITY_LOADER_LEGACY;

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
     * @return static
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder, string $alias)
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

    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    public function getAlias(): string
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
     * @return static
     */
    public function setEntityLoaderMode(int $entityLoaderMode)
    {
        $this->entityLoaderMode = $entityLoaderMode;

        return $this;
    }

    public function getEntityLoaderMode(): int
    {
        return $this->entityLoaderMode;
    }

    /**
     * @return static
     */
    public function setEntityLoaderRepository(string $entityLoaderRepository)
    {
        // force mode
        $this->setEntityLoaderMode(self::ENTITY_LOADER_REPOSITORY);

        $this->entityLoaderRepository = $entityLoaderRepository;

        return $this;
    }

    public function getEntityLoaderRepository(): string
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

    public function haveTotalColumns(): bool
    {
        foreach ($this->getColumns() as $column) {
            if ($column->isUseTotal()) {
                return true;
            }
        }

        return false;
    }
}
