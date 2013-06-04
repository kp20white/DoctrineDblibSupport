<?php

namespace FAMC\Bundle\DoctrineDblibSupportBundle\Doctrine\ORM\Mapping\Driver;

use FAMC\Bundle\DoctrineDblibSupportBundle\Doctrine\DBAL\Schema\SQLServerSchemaManager, 
    Doctrine\DBAL\Schema\AbstractSchemaManager,
    Doctrine\ORM\Mapping\Driver\DatabaseDriver as DoctrineDatabaseDriver, 
    Doctrine\Common\Persistence\Mapping\ClassMetadata,
    Doctrine\ORM\Mapping\ClassMetadataInfo,
    Doctrine\Common\Util\Inflector,
    Doctrine\ORM\Mapping\MappingException
    ;

class DatabaseDriver extends DoctrineDatabaseDriver {
    /**
     * @var AbstractSchemaManager
     */
    private $_sm;
    
    private $databaseName = null;
    
    private $schemaName = null;

    private $generateRepositoryClass = false;

    /**
     * @var array
     */
    private $tables = null;

    private $classToDatabaseNames = array();
    
    private $classToTableNames = array();
    
    private $classToSchemaNames = array();

    /**
     * @var array
     */
    private $manyToManyTables = array();

    /**
     * @var array
     */
    private $classNamesForTables = array();

    /**
     * @var array
     */
    private $fieldNamesForColumns = array();

    /**
     * The namespace for the generated entities.
     *
     * @var string
     */
    private $namespace;

    /**
     *
     * @param SQLServerSchemaManager $schemaManager
     */
    public function __construct(SQLServerSchemaManager $schemaManager) {
        $this->_sm = $schemaManager;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllClassNames()
    {
        $this->reverseEngineerMappingFromDatabase();

        return array_keys($this->classToTableNames);
    }

    /**
     * {@inheritDoc}
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata)
    {
        $this->reverseEngineerMappingFromDatabase();

        if (!isset($this->classToTableNames[$className])) {
            throw new \InvalidArgumentException("Unknown class " . $className);
        }

        $databaseName = $this->classToDatabaseNames[$className];
        $schemaName = $this->classToSchemaNames[$className];        
        $tableName = $this->classToTableNames[$className];
        
        $metadata->name = $className;
        $metadata->table['name'] = $databaseName . '.' . $schemaName . '.' . $tableName;

        if ($this->generateRepositoryClass) {
            $metadata->customRepositoryClassName = $className . 'Repository';
        }

        $columns = $this->tables[$tableName]->getColumns();
        $indexes = $this->tables[$tableName]->getIndexes();
        try {
            $primaryKeyColumns = $this->tables[$tableName]->getPrimaryKey()->getColumns();
        } catch(SchemaException $e) {
            $primaryKeyColumns = array();
        }

        if ($this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
            $foreignKeys = $this->tables[$tableName]->getForeignKeys();
        } else {
            $foreignKeys = array();
        }

        $allForeignKeyColumns = array();
        foreach ($foreignKeys as $foreignKey) {
            $allForeignKeyColumns = array_merge($allForeignKeyColumns, $foreignKey->getLocalColumns());
        }

        $ids = array();
        $fieldMappings = array();
        foreach ($columns as $column) {
            $fieldMapping = array();

            if (in_array($column->getName(), $allForeignKeyColumns)) {
                continue;
            } else if ($primaryKeyColumns && in_array($column->getName(), $primaryKeyColumns)) {
                $fieldMapping['id'] = true;
            }

            $fieldMapping['fieldName'] = $this->getFieldNameForColumn($tableName, $column->getName(), false);
            $fieldMapping['columnName'] = $column->getName();
            $fieldMapping['type'] = strtolower((string) $column->getType());

            if ($column->getType() instanceof \Doctrine\DBAL\Types\StringType) {
                $fieldMapping['length'] = $column->getLength();
                $fieldMapping['fixed'] = $column->getFixed();
            } else if ($column->getType() instanceof \Doctrine\DBAL\Types\IntegerType) {
                $fieldMapping['unsigned'] = $column->getUnsigned();
            }
            $fieldMapping['nullable'] = $column->getNotNull() ? false : true;

            if (isset($fieldMapping['id'])) {
                $ids[] = $fieldMapping;
            } else {
                $fieldMappings[] = $fieldMapping;
            }
        }

        if ($ids) {
            if (count($ids) == 1) {
                $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);
            }

            foreach ($ids as $id) {
                $metadata->mapField($id);
            }
        }

        foreach ($fieldMappings as $fieldMapping) {
            $metadata->mapField($fieldMapping);
        }

        foreach ($this->manyToManyTables as $manyTable) {
            foreach ($manyTable->getForeignKeys() as $foreignKey) {
                // foreign  key maps to the table of the current entity, many to many association probably exists
                if (strtolower($tableName) == strtolower($foreignKey->getForeignTableName())) {
                    $myFk = $foreignKey;
                    $otherFk = null;
                    foreach ($manyTable->getForeignKeys() as $foreignKey) {
                        if ($foreignKey != $myFk) {
                            $otherFk = $foreignKey;
                            break;
                        }
                    }

                    if (!$otherFk) {
                        // the definition of this many to many table does not contain
                        // enough foreign key information to continue reverse engeneering.
                        continue;
                    }

                    $localColumn = current($myFk->getColumns());
                    $associationMapping = array();
                    $associationMapping['fieldName'] = $this->getFieldNameForColumn($manyTable->getName(), current($otherFk->getColumns()), true);
                    $associationMapping['targetEntity'] = $this->getClassNameForTable($otherFk->getForeignTableName());
                    if (current($manyTable->getColumns())->getName() == $localColumn) {
                        $associationMapping['inversedBy'] = $this->getFieldNameForColumn($manyTable->getName(), current($myFk->getColumns()), true);
                        $associationMapping['joinTable'] = array(
                            'name' => strtolower($manyTable->getName()),
                            'joinColumns' => array(),
                            'inverseJoinColumns' => array(),
                        );

                        $fkCols = $myFk->getForeignColumns();
                        $cols = $myFk->getColumns();
                        for ($i = 0; $i < count($cols); $i++) {
                            $associationMapping['joinTable']['joinColumns'][] = array(
                                'name' => $cols[$i],
                                'referencedColumnName' => $fkCols[$i],
                            );
                        }

                        $fkCols = $otherFk->getForeignColumns();
                        $cols = $otherFk->getColumns();
                        for ($i = 0; $i < count($cols); $i++) {
                            $associationMapping['joinTable']['inverseJoinColumns'][] = array(
                                'name' => $cols[$i],
                                'referencedColumnName' => $fkCols[$i],
                            );
                        }
                    } else {
                        $associationMapping['mappedBy'] = $this->getFieldNameForColumn($manyTable->getName(), current($myFk->getColumns()), true);
                    }
                    $metadata->mapManyToMany($associationMapping);
                    break;
                }
            }
        }

        foreach ($foreignKeys as $foreignKey) {
            $foreignTable = $foreignKey->getForeignTableName();
            $cols = $foreignKey->getColumns();
            $fkCols = $foreignKey->getForeignColumns();

            $localColumn = current($cols);
            $associationMapping = array();
            $associationMapping['fieldName'] = $this->getFieldNameForColumn($tableName, $localColumn, true);
            $associationMapping['targetEntity'] = $this->getClassNameForTable($foreignTable);

            if ($primaryKeyColumns && in_array($localColumn, $primaryKeyColumns)) {
                $associationMapping['id'] = true;
            }

            for ($i = 0; $i < count($cols); $i++) {
                $associationMapping['joinColumns'][] = array(
                    'name' => $cols[$i],
                    'referencedColumnName' => $fkCols[$i],
                );
            }

            //Here we need to check if $cols are the same as $primaryKeyColums
            if (!array_diff($cols,$primaryKeyColumns)) {
                $metadata->mapOneToOne($associationMapping);
            } else {
                $metadata->mapManyToOne($associationMapping);
            }
        }
    }
    
    public function setDatabaseName($databaseName) {
        $this->databaseName = $databaseName;
        return $this;
    }
    
    public function setSchemaName($schemaName) {
        $this->schemaName = $schemaName;
        return $this;
    }

    public function setGenerateRepositoryClass($bool = false) {
        $this->generateRepositoryClass = $bool;
    }

    protected function reverseEngineerMappingFromDatabase()
    {
        if ($this->tables !== null) {
            return;
        }

        $tables = array();

        $tableNames = $this->_sm->listTableNamesPerDatabase($this->databaseName, $this->schemaName);
        foreach ($tableNames as $tableName) {
            $tables[$tableName] = $this->_sm->listTableDetailsPerDatabase($tableName, $this->databaseName, $this->schemaName );
        }

        $this->tables = array();
        $this->manyToManyTables = array();
        $this->classToTableNames = array();
        $this->classToSchemaNames = array();
        $this->classToDatabaseNames = array();

        foreach ($tables as $tableName => $table) {
            /* @var $table \Doctrine\DBAL\Schema\Table */
            if ($this->_sm->getDatabasePlatform()->supportsForeignKeyConstraints()) {
                $foreignKeys = $table->getForeignKeys();
            } else {
                $foreignKeys = array();
            }

            $allForeignKeyColumns = array();
            foreach ($foreignKeys as $foreignKey) {
                $allForeignKeyColumns = array_merge($allForeignKeyColumns, $foreignKey->getLocalColumns());
            }

            /**
             * Instead of throwing a MappingException here,
             * we just want to ignore tables that don't have
             * primary keys.  I mean, you can take a dump
             * in a box and slap a guarantee on it, but all 
             * you have is a guaranteed piece of shit.
             */
            if ( ! $table->hasPrimaryKey()) {
                unset($tables[$tableName]);
                $table = null;
                continue;
            }

            $pkColumns = $table->getPrimaryKey()->getColumns();
            sort($pkColumns);
            sort($allForeignKeyColumns);

            if ($pkColumns == $allForeignKeyColumns && count($foreignKeys) == 2) {
                $this->manyToManyTables[$tableName] = $table;
            } else {
                // lower-casing is necessary because of Oracle Uppercase Tablenames,
                // assumption is lower-case + underscore separated.
                $className = $this->getClassNameForTable($tableName);
                $this->tables[$tableName] = $table;
                $this->classToTableNames[$className] = $tableName;
                $this->classToSchemaNames[$className] = $this->schemaName;
                $this->classToDatabaseNames[$className] = $this->databaseName;
            }
        }
    }

    /**
     * Return the mapped class name for a table if it exists. Otherwise return "classified" version.
     *
     * @param string $tableName
     * @return string
     */
    private function getClassNameForTable($tableName)
    {
        if (isset($this->classNamesForTables[$tableName])) {
            return $this->namespace . $this->classNamesForTables[$tableName];
        }

        return $this->namespace . Inflector::classify(strtolower($tableName));
    }

    /**
     * Return the mapped field name for a column, if it exists. Otherwise return camelized version.
     *
     * @param string $tableName
     * @param string $columnName
     * @param boolean $fk Whether the column is a foreignkey or not.
     * @return string
     */
    private function getFieldNameForColumn($tableName, $columnName, $fk = false)
    {
        if (isset($this->fieldNamesForColumns[$tableName]) && isset($this->fieldNamesForColumns[$tableName][$columnName])) {
            return $this->fieldNamesForColumns[$tableName][$columnName];
        }

        $columnName = strtolower($columnName);

        // Replace _id if it is a foreignkey column
        if ($fk) {
            $columnName = str_replace('_id', '', $columnName);
        }
        return Inflector::camelize($columnName);
    }
}
