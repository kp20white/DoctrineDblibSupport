<?php

namespace FAMC\Bundle\DoctrineDblibSupportBundle\Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLServer2008Platform as DoctrineSQLServer2008Platform;

class SQLServerPlatformFamc extends DoctrineSQLServer2008Platform {
    public function getListTableSchemasPerDatabaseSQL($databaseName) {
        return "SELECT table_schema FROM $databaseName.information_schema.tables group by table_schema";
    }
    
    public function getListTablesPerDatabaseSQL($databaseName) {
        return "SELECT name FROM $databaseName.sys.objects WHERE type = 'U' AND name NOT IN ('_Fillfactor_Tables', '_Indexes', 'DBA_ChangeCntrl','DBA_ChangeCntrl_Hist','dba_SchemaVerCntrl')";
    }

    /**
     * 
     * @param $table the table that we're searching for indexes
     * @param $databaseName the database to query on if set
     */
    public function getListTableIndexesPerDatabaseSQL($table, $databaseName)
    {
        $sql = "exec $databaseName.sys.sp_helpindex '$table'";
        return $sql;
    }

    public function getListTableColumnsPerDatabaseSQL($tableName, $databaseName) {
        
        $sql = "exec $databaseName.sys.sp_columns @table_name = '$tableName'";
        return $sql;
    }

    /**
     * @override
     */
    public function getListTableForeignKeysPerDatabaseSQL($tableName, $databaseName) {
        $sql = parent::getListTableForeignKeysSQL($tableName); 
//        $sql = preg_replace("/(\ )(sys\.)/", "$1$databaseName.$2", $sql);
        return $sql;
    }
}
