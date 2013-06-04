<?php

namespace FAMC\Bundle\DoctrineDblibSupportBundle\Doctrine\DBAL\Schema;

use Doctrine\DBAL\Events,
    Doctrine\DBAL\Schema\SQLServerSchemaManager as DoctrineSQLServerSchemaManager,
    Doctrine\DBAL\Schema\Column,
    Doctrine\DBAL\Schema\Index,
    Doctrine\DBAL\Schema\Table
    ;

class SQLServerSchemaManager extends DoctrineSQLServerSchemaManager {
    private $tableIndexesExist = array();
    
    /**
     * Return a list of all tables in the current database
     *
     * @return array
     */
    public function listTableNamesPerDatabase($databaseName, $schema = null)
    {
        $sql = $this->_platform->getListTablesPerDatabaseSQL($databaseName);

        $tables = $this->_conn->fetchAll($sql);

        $tableNames = $this->_getPortableTablesList($tables);

        // check if table indexes exist
        foreach ($tableNames as $tableName) {
            if(!is_null($schema)) {
                $tableName = sprintf("%s.%s", $schema,$tableName);
            }

            $sql = $this->_platform->getListTableIndexesPerDatabaseSQL($tableName, $databaseName);

            $indexNames = $this->_conn->fetchAll($sql);

            if (count($indexNames) > 0) {
                $this->tableIndexesExist[$databaseName] = true;
            }
        }

        return $this->filterAssetNames($tableNames);
    }


    /**
     * List the tables for this connection
     *
     * @return Table[]
     */
    public function listTablesPerDatabase($databaseName)
    {
        $tableNames = $this->listTableNamesPerDatabase($databaseName);

        $tables = array();
        foreach ($tableNames as $tableName) {
            $tables[] = $this->listTableDetailsPerDatabase($tableName, $databaseName);
        }

        return $tables;
    }

    /**
     * @param  string $tableName
     * @return Table
     */
    public function listTableDetailsPerDatabase($tableName, $databaseName, $schema = null) {
        $columns = $this->listTableColumnsPerDatabase($tableName, $databaseName);
        $foreignKeys = array();
        if ($this->_platform->supportsForeignKeyConstraints()) {
            $foreignKeys = $this->listTableForeignKeysPerDatabase($tableName, $databaseName);
        }
        if(!is_null($schema)){
            $tableName = sprintf("%s.%s", $schema, $tableName);
        }
        $indexes = $this->listTableIndexesPerDatabase($tableName, $databaseName);

        return new Table($tableName, $columns, $indexes, $foreignKeys, false, array());
    }

    /**
     * List the columns for a given table.
     *
     * In contrast to other libraries and to the old version of Doctrine,
     * this column definition does try to contain the 'primary' field for
     * the reason that it is not portable accross different RDBMS. Use
     * {@see listTableIndexes($tableName)} to retrieve the primary key
     * of a table. We're a RDBMS specifies more details these are held
     * in the platformDetails array.
     *
     * @param string $table The name of the table.
     * @param string $database
     * @return Column[]
     */
    public function listTableColumnsPerDatabase($tableName, $databaseName) {
        $sql = $this->_platform->getListTableColumnsPerDatabaseSQL($tableName, $databaseName);

        $tableColumns = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableColumnList($tableName, $databaseName, $tableColumns);
    }

    /**
     * List the indexes for a given table returning an array of Index instances.
     *
     * Keys of the portable indexes list are all lower-cased.
     *
     * @param string $table The name of the table
     * @return Index[] $tableIndexes
     */
    public function listTableIndexesPerDatabase($tableName, $databaseName) {
        if (isset($this->tableIndexesExist[$databaseName]) && $this->tableIndexesExist[$databaseName]) {
            $sql = $this->_platform->getListTableIndexesSQL($tableName, $databaseName);
            $tableIndexes = $this->_conn->fetchAll($sql);

            return $this->_getPortableTableIndexesList($tableIndexes, $tableName);
        } else {
            return array();
        }
    }

    /**
     * List the foreign keys for the given table
     *
     * @param string $table  The name of the table
     * @return ForeignKeyConstraint[]
     */
    public function listTableForeignKeysPerDatabase($tableName, $databaseName) {
        $this->_conn->exec("use $databaseName");
        $sql = $this->_platform->getListTableForeignKeysPerDatabaseSQL($tableName, $databaseName);

        $tableForeignKeys = $this->_conn->fetchAll($sql);

        return $this->_getPortableTableForeignKeysList($tableForeignKeys);
    }
    
    public function listTableSchemasPerDatabase($databaseName) {
        $sql = $this->_platform->getListTableSchemasPerDatabaseSQL($databaseName);
        
        $schemas = $this->_conn->fetchAll($sql);
    
        $schemaNames = array();
        foreach ($schemas as $schema) {
            $schemaNames[] = $schema['table_schema'];
        }   

        return $schemaNames;
    }
}
