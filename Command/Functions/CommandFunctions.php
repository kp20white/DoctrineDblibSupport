<?php

namespace FAMC\Bundle\DoctrineDblibSupportBundle\Command\Functions;

use
    FAMC\Bundle\UtilityBundle\Classes\UtilityFunctions,
    Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper,
    Symfony\Component\Console\Input\InputDefinition
;

class CommandFunctions {
    /** 
     * remove given existing options so we can setup 
     * new instances of the options that include
     * default values
     * 
     * @param InputDefinition $definition command input definition
     * @param array|mixed $existingOptionNames options to remove from the definition
     * 
     * @author Kyle White <kwhite@franklinamerican.com>
     */
    static public function removeOptions(InputDefinition &$definition, array $existingOptionNames) {
        
        $currentOptions = $definition->getOptions();
        $currentOptionKeys = array_keys($currentOptions);
        for ( $i = 0; $i < count($currentOptionKeys); $i++) {
            $currentOptionKey = $currentOptionKeys[$i];
            if (UtilityFunctions::in_arrayi($currentOptionKey, $existingOptionNames)) {
                unset($currentOptions[$currentOptionKey]); 
            } 
        }
        $definition->setOptions($currentOptions);
    }
    
    /** 
     * remove given existing arguments so we can setup 
     * new instances of the arguments that include
     * default values
     * 
     * @param InputDefinition $definition command input definition
     * @param array|mixed $existingArgumentNames options to remove from the definition
     * 
     * @author Kyle White <kwhite@franklinamerican.com>
     */
    static public function removeArguments(InputDefinition &$definition, array $existingArgumentNames) {
        
        $currentArguments = $definition->getArguments();
        $currentArgumentKeys = array_keys($currentArguments);
        for ( $i = 0; $i < count($currentArgumentKeys); $i++) {
            $currentArgumentKey = $currentArgumentKeys[$i];
            if (UtilityFunctions::in_arrayi($currentArgumentKey, $existingArgumentNames)) {
                unset($currentArguments[$currentArgumentKey]); 
            } 
        }
        $definition->setArguments($currentArguments);
    }
}
