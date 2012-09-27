<?php

/*
 * ImportMappingFamcValidator
 *
 * (c) Kyle White <kwhite@franklinamerican.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FAMC\Bundle\DoctrineDblibSupportBundle\Command;

use FAMC\UtilityBundle\Classes\UtilityFunctions, 
    Doctrine\ORM\EntityManager,
    Sensio\Bundle\GeneratorBundle\Command\Validators
    ;

/**
 * Validator methods.
 *
 * @author Kyle White <kwhite@franklinamerican.com>
 */
class ImportMappingFamcValidator {
    private $em = null;
    private $selectedDatabase = null;

    public function __construct(EntityManager $em) {
       $this->em = $em; 
       $this->selectionErrorMessage = 
       'Selected %s must be one of %s. ' . 
       PHP_EOL .  PHP_EOL .  'You entered %s.' .  PHP_EOL;
    }

    public function validateSelectedDatabase($selectedDatabase) {
        $databaseNames = $this->em->getConnection()->getSchemaManager()->listDatabases();

        if (empty($selectedDatabase)) {
            $this->reportSelectionError($databaseNames, $selectedDatabase, "database", "nothing");
        }

        if (!UtilityFunctions::in_arrayi($selectedDatabase, $databaseNames)) {
            $this->reportSelectionError($databaseNames, $selectedDatabase, "database");
        }

        return $selectedDatabase;
    }

    public function validateSelectedSchema($selectedSchema) {
        $schemaNames = $this->em->getConnection()->getSchemaManager()->listTableSchemasPerDatabase($this->selectedDatabase);

        if ("all" == $selectedSchema) {
            $this->reportSelectionError($schemaNames, $selectedSchema, "schema");
        }

        if (!UtilityFunctions::in_arrayi($selectedSchema, $schemaNames)) {
            $this->reportSelectionError($schemaNames, $selectedSchema, "schema");
        }

        return $selectedSchema;
    }

    public function validateMappingType($mappingType) {
        $mappingTypeOptions = array("yml", "yaml", "xml", "annotation");

        if (empty($mappingType)) {
            $this->reportSelectionError($mappingTypeOptions, $mappingType, "mapping-type", "nothing");
        }

        if (!UtilityFunctions::in_arrayi($mappingType, $mappingTypeOptions)) {
            $this->reportSelectionError($mappingTypeOptions, $mappingType, "mapping-type");
        }

        return $mappingType;
    }

    public function validateDestPath($destPath) {
        if (empty($destPath)) {
            throw new \InvalidArgumentException("\n\ndest-path cannot be empty. Enter a valid bundle path. E.g. src/myVendor/MyAwesomeBundle\n\n");
        }
        
        return $destPath;
    }

    protected function reportSelectionError(array $targetData, $userSelection, $selectionDescription, $altSelectionMsg = "") {
        $msg = array();
        $msg[] = sprintf($this->selectionErrorMessage, $selectionDescription,
                        implode(',', $targetData), 
                        !empty($altSelectionMsg) ? $altSelectionMsg : $userSelection);

        throw new \InvalidArgumentException(implode("\n\n", $msg));
    }

    public function setSelectedDatabase($selectedDatabase) {
        $this->selectedDatabase = $selectedDatabase;
        return $this;
    }
}
