<?php

namespace Eukles\Entity;

use Eukles\Container\ContainerInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\Exception\RelationNotFoundException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;

/**
 * ActiveRecordRequestInterface Base class
 *
 * @package Core\Model
 */
abstract class EntityRequestAbstract implements EntityRequestInterface
{
    
    /**
     * @var ContainerInterface
     */
    protected $container;
    /**
     * @var array
     */
    protected $exposedProperties;
    /**
     * @var array
     */
    protected $exposedRelations;
    /**
     * @var int|string
     */
    protected $pk;
    /**
     * @var array
     */
    protected $relations = [];
    /**
     * @var bool
     */
    protected $relationsBuilt = false;
    
    /**
     * @inheritdoc
     */
    public function __construct(ContainerInterface $c)
    {
        $this->container = $c;
    }
    
    /**
     * @return string
     */
    abstract public function getActionClassName();
    
    /**
     * All properties of ActiveRecord
     *
     * @return array
     * @throws \Propel\Runtime\Exception\PropelException
     */
    abstract protected function getAllProperties();
    
    /**
     * Set state of the object after request data hydration
     *
     * @param ActiveRecordInterface $obj
     *
     */
    public function afterCreate(ActiveRecordInterface $obj)
    {
    }
    
    /**
     * Set state of the object after request data hydration
     *
     * @param ActiveRecordInterface $obj
     *
     */
    public function afterFetch(ActiveRecordInterface $obj)
    {
    }
    
    /**
     * Set state of the object before request data hydration
     *
     * @param ActiveRecordInterface $obj
     *
     */
    public function beforeCreate(ActiveRecordInterface $obj)
    {
    }
    
    /**
     * Set state of the object before request data hydration
     *
     * @param ModelCriteria $query
     *
     * @return ModelCriteria
     *
     */
    public function beforeFetch(ModelCriteria $query)
    {
        return $query;
    }
    
    /**
     * None, all or partial list of properties
     *
     * @return array List of modifiable properties
     */
    public function getModifiableProperties()
    {
        return $this->getWritableProperties();
    }
    
    /**
     * None, all or partial list of properties
     *
     * @return array List of writable properties
     */
    public function getRequiredWritableProperties()
    {
        return $this->getWritableProperties();
    }
    
    /**
     * None, all or partial list of properties
     *
     * @return array List of writable properties
     */
    public function getWritableProperties()
    {
        return $this->getAllProperties();
    }
    
    /**
     * Hydrates an ActiveRecord with filtered Request params
     *
     * @param array  $requestParams
     * @param string $httpMethod
     *
     * @return array
     */
    final public function getAllowedDataFromRequest(array $requestParams, $httpMethod)
    {
        if (in_array($httpMethod, ['PATCH', 'PUT'])) {
            $properties = $this->getModifiableProperties();
        } elseif ($httpMethod === 'POST') {
            $properties = $this->getWritableProperties();
        } else {
            return [];
        }
        
        $data = [];
        if (false === empty($properties)) {
            foreach ($properties as $property) {
                $lcProperty = lcfirst($property);
                if (isset($requestParams[$lcProperty])) {
                    $data[$property] = $requestParams[$lcProperty];
                }
            }
        }
        
        return $data;
    }
    
    /**
     * @return ContainerInterface
     */
    final public function getContainer()
    {
        return $this->container;
    }
    
    /**
     * None, all or partial list of properties
     *
     * Adds visible fields, and remove hidden Properties
     *
     * @return array List of visible properties
     */
    final public function getExposedProperties()
    {
        if (null === $this->exposedProperties) {
            $this->exposedProperties = array_diff($this->getVisibleFields(), $this->getHiddenProperties());
        }
        
        return $this->exposedProperties;
    }
    
    /**
     * None, all or partial list of relations
     *
     * Adds visible relations, and remove hidden Properties
     *
     * @return array List of visible properties
     */
    final public function getExposedRelations()
    {
        if (null === $this->exposedRelations) {
            $this->exposedRelations = array_diff($this->getRelationsNames(), $this->getHiddenProperties());
        }
        
        return $this->exposedRelations;
    }
    
    /**
     * @return int|string
     */
    final public function getPrimaryKey()
    {
        return $this->pk;
    }
    
    /**
     * @inheritdoc
     */
    final public function getRelation($name)
    {
        if (!array_key_exists($name, $this->getRelations())) {
            throw new RelationNotFoundException(sprintf('Calling getRelation() on an unknown relation: %s.', $name));
        }
        
        return $this->relations[$name];
    }
    
    /**
     * @inheritdoc
     */
    final public function getRelationType($name)
    {
        return $this->getRelation($name)->getType();
    }
    
    /**
     * Returns names of relations
     *
     * Build list once and cache
     *
     * @return array
     */
    final public function getRelations()
    {
        if ($this->relationsBuilt === false) {
            $tableMap = $this->getTableMap();
            $this->buildRelations($tableMap);
            $this->relationsBuilt = true;
        }
        
        return $this->relations;
    }
    
    /**
     * @inheritdoc
     */
    final public function getRelationsNames()
    {
        return array_keys($this->getRelations());
    }
    
    /**
     * @inheritdoc
     */
    final public function hasRelations()
    {
        return empty($this->getRelations()) === false;
    }
    
    /**
     * @inheritdoc
     */
    final public function isPluralRelation($name)
    {
        return in_array($this->getRelationType($name), [RelationMap::ONE_TO_MANY, RelationMap::MANY_TO_MANY]);
    }
    
    /**
     * @inheritdoc
     */
    final public function isRelation($name)
    {
        return array_key_exists($name, $this->getRelations());
    }
    
    /**
     * @inheritdoc
     */
    final public function isRelationManyToOne($name)
    {
        return $this->getRelation($name)->getType() === RelationMap::MANY_TO_ONE;
    }
    
    /**
     * @inheritdoc
     */
    final public function isRelationOneToMany($name)
    {
        return $this->getRelation($name)->getType() === RelationMap::ONE_TO_MANY;
    }
    
    /**
     * @inheritdoc
     */
    final public function isRelationOneToOne($name)
    {
        return $this->getRelation($name)->getType() === RelationMap::ONE_TO_ONE;
    }
    
    /**
     * @inheritdoc
     */
    final public function setPrimaryKey($pk)
    {
        $this->pk = $pk;
        
        return $this;
    }
    
    /**
     * @param TableMap $tableMap
     */
    final protected function buildRelations(TableMap $tableMap)
    {
        # init to array to don't pass tests on null
        $this->relations = [];
        
        foreach ($tableMap->getRelations() as $relation) {
            if ($relation->getType() === RelationMap::ONE_TO_MANY) {
                $this->relations[$relation->getPluralName()] = $relation;
            } else {
                $this->relations[$relation->getName()] = $relation;
            }
        }
    }
    
    /**
     * None, all or partial list of properties
     *
     * @return array List of hidden properties
     */
    protected function getHiddenFieldsAndRelations()
    {
        return [];
    }
    
    /**
     * None, all or partial list of fields
     *
     * @return array List of visible properties
     */
    protected function getVisibleFields()
    {
        return $this->getAllProperties();
    }
}
