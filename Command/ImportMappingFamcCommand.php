<?php

namespace FAMC\Bundle\DoctrineDblibSupportBundle\Command;

use FAMC\Bundle\DoctrineDblibSupportBundle\Doctrine\ORM\Mapping\Driver\DatabaseDriver,
    FAMC\Bundle\DoctrineDblibSupportBundle\Command\Functions\CommandFunctions,
    Doctrine\Bundle\DoctrineBundle\Command\ImportMappingDoctrineCommand,
    Doctrine\Bundle\DoctrineBundle\Command\Proxy\DoctrineCommandHelper,
    Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper,
    Symfony\Component\Console\Helper\HelperSet,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Doctrine\ORM\Tools\DisconnectedClassMetadataFactory,
    Doctrine\ORM\Tools\Export\ClassMetadataExporter,
    Doctrine\ORM\Tools\Console\MetadataFilter
    ;
class ImportMappingFamcCommand extends ImportMappingDoctrineCommand {

    /**
     * {@inheritDoc}
     */
    protected function configure() {
        parent::configure();

        $helpText = preg_replace("/doctrine/", "famc", $this->getHelp());

        $this
        ->setName("famc:mapping:import")
        ->setHelp(<<<EOT
$helpText
EOT
        );

        $definition = $this->getDefinition();
        $emOption = $definition->getOption("em");

        CommandFunctions::removeOptions($definition, array("em"));
        $additionalOptions = array(
            new InputOption(
                'selected-database', 
                null, 
                InputOption::VALUE_REQUIRED,
                'A SQL Server database which will be scanned to convert tables into mapping files.'
            ),
            new InputOption(
                'selected-schema', 
                null, 
                InputOption::VALUE_REQUIRED,
                'The SQL Server schema to use for your databases. E.g. "dbo", "information_schema", etc.',
                'dbo'
            ),
            new InputOption(
                "em", 
                $emOption->getShortcut(),
                InputOption::VALUE_REQUIRED, 
                $emOption->getDescription(),
                $emOption->getDefault()
            ),
        );
        $definition->addOptions($additionalOptions);

        $this->setDefinition($definition);
    }

    protected function initialize(InputInterface $input, OutputInterface $output) {
        parent::initialize($input, $output);

        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));
        $this->entityManager = $this->getHelper('em')->getEntityManager();
    }

    /**
     * this is dumb. i have to override because there's no dependency injection for the 
     * database driver.  poopstix to that.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('bundle'));
        $destPath = $bundle->getPath();
        $type = $input->getArgument('mapping-type') ? $input->getArgument('mapping-type') : 'yml';
        if ('annotation' === $type) {
            $destPath .= '/Entity';
        } else {
            $destPath .= '/Resources/config/doctrine';
        }
        if ('yaml' === $type) {
            $type = 'yml';
        }

        $cme = new ClassMetadataExporter();
        $exporter = $cme->getExporter($type);
        $exporter->setOverwriteExistingFiles($input->getOption('force'));

        if ('annotation' === $type) {
            $entityGenerator = $this->getEntityGenerator();
            $entityGenerator->setGenerateStubMethods(true);
            $exporter->setEntityGenerator($entityGenerator);
        }

        $em = $this->getEntityManager($input->getOption('em'));

        //add selected database and schema shit to database driver
        $databaseDriver = new DatabaseDriver(
                $em->getConnection()->getSchemaManager()
                );
        $databaseDriver->setDatabaseName($input->getOption('selected-database'));
        $databaseDriver->setSchemaName($input->getOption('selected-schema'));

        $em->getConfiguration()->setMetadataDriverImpl(
                $databaseDriver
                );

/*
        if (($namespace = $input->getOption('namespace')) !== null) {
            $databaseDriver->setNamespace($namespace);
        }
*/

        $emName = $input->getOption('em');
        $emName = $emName ? $emName : 'default';

        $cmf = new DisconnectedClassMetadataFactory();
        $cmf->setEntityManager($em);
        $metadata = $cmf->getAllMetadata();
        $metadata = MetadataFilter::filter($metadata, $input->getOption('filter'));
        if ($metadata) {
            $output->writeln(sprintf('Importing mapping information from "<info>%s</info>" entity manager', $emName));
            foreach ($metadata as $class) {
                $className = $class->name;
                $class->name = $bundle->getNamespace().'\\Entity\\'.$className;
                if ('annotation' === $type) {
                    $path = $destPath.'/'.$className.'.php';
                } else {
                    $path = $destPath.'/'.$className.'.orm.'.$type;
                }
                $output->writeln(sprintf('  > writing <comment>%s</comment>', $path));
                $code = $exporter->exportClassMetadata($class);
                if (!is_dir($dir = dirname($path))) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($path, $code);
            }
        } else {
            $output->writeln('Database does not have any mapping information.', 'ERROR');
            $output->writeln('', 'ERROR');
        }
    }


    protected function interact(InputInterface $input, OutputInterface $output) {
        $em = is_null($this->entityManager) ? $this->getHelper('em')->getEntityManager() : $this->entityManager;

        $dialog = $this->getHelperSet()->get("dialog");
        if (!$dialog || get_class($dialog) !== 'Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper') {
            $dialog = new DialogHelper();
            $this->getHelperSet()->set($dialog, "dialog");
        }

        $dialog->writeSection($output, 'Welcome to the FAMC SQL Server Mapping Generator');

        // namespace
        $output->writeln(array(
            '',
            'Existing Doctrine2 support for SQL Server databases does not work with',
            'a setup of managing multiple databases within an application.',
            '',
            'This bundle will help you convert your existing database into mapping files',
            'which can later be used to generate Entity classes.',
            '',
        ));

        $validator = new ImportMappingFamcValidator($em);
        $selectedDatabase = $dialog->askAndValidate(
            $output, 
            $dialog->getQuestion('[selected-database] Select database - Press enter with no input to see available databases', 
            $input->getOption('selected-database')), 
            array($validator, 'validateSelectedDatabase'), 
            false, 
            $input->getOption('selected-database')
        );
        $input->setOption('selected-database', $selectedDatabase);
        $validator->setSelectedDatabase($selectedDatabase);

        $selectedSchema = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion('[selected-schema] Select schema - Enter "all" see available schemas',
                $input->getOption('selected-schema')),
            array($validator, 'validateSelectedSchema'),
            false,
            $input->getOption('selected-schema')
        );
        $input->setOption('selected-schema', $selectedSchema);

        $mappingType = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion('[mapping-type] Select mapping output type - Enter "all" see available types',
                $input->getArgument('mapping-type')),
            array($validator, 'validateMappingType'),
            false,
            $input->getArgument('mapping-type')
        );
        $input->setArgument('mapping-type', $mappingType);
    }
}
