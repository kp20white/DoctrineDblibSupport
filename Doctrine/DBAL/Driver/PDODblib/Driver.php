<?php 

/**
 * Driver
 * 
 * A driver class to support the pdo_dblib
 * driver for MS SQL Server connection in *NIX
 * systems since it is not currently supported
 * by Doctrine2. 
 * 
 * @package FAMC\Bundle\DoctrineDblibSupportBundle
 * @author Kyle White <kwhite@franklinamerican.com>
 * @date 3 August 2012
 * 
 */

namespace FAMC\Bundle\DoctrineDblibSupportBundle\Doctrine\DBAL\Driver\PDODblib;

use Doctrine\DBAL\Driver as DoctrineDbalDriver,
    FAMC\Bundle\DoctrineDblibSupportBundle\Doctrine\DBAL\Schema\SQLServerSchemaManager,
    FAMC\Bundle\DoctrineDblibSupportBundle\Doctrine\DBAL\Platforms\SQLServerPlatformFamc
    ;

class Driver implements DoctrineDBALDriver {
    public function connect(array $params, $username = null, $password = null, array $driverOptions = array()) {
        return new Connection(
            $this->_constructPdoDsn($params),
            $username,
            $password,
            $driverOptions
        );
    }

    /**
     * Constructs the Sqlsrv PDO DSN.
     *
     * @return string  The DSN.
     */
    private function _constructPdoDsn(array $params) {
        $dsn = 'dblib:host=';

        if (isset($params['host'])) {
            $dsn .= $params['host'];
        }

        if (isset($params['port']) && !empty($params['port'])) {
            $dsn .= ':' . $params['port'];
        }

        if (isset($params['dbname'])) {;
            $dsn .= ';Database=' .  $params['dbname'];
        }

        if (isset($params['MultipleActiveResultSets'])) {
            $dsn .= '; MultipleActiveResultSets=' . ($params['MultipleActiveResultSets'] ? 'true' : 'false');
        }

        return $dsn;
    }

    public function getDatabasePlatform() {
        return new SQLServerPlatformFamc();
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn) {
        return new SQLServerSchemaManager($conn);
    }

    public function getName() {
        return 'pdo_dblib';
    }

    public function getDatabase(\Doctrine\DBAL\Connection $conn) {
        $params = $conn->getParams();
        if (isset($params['dbname'])) {;
            return $params['dbname'];
        } else {
            return null;
        }
    }
}
