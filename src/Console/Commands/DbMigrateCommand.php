<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Configs\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Console\PhinxApplication;

class DbMigrateCommand extends Command
{
    protected static $defaultName = 'db:migrate';
    protected $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run Phinx commands')
            ->setHelp('This command allows you to run Phinx commands like migrate, rollback, etc.')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'The path to the phinx.php configuration file', __DIR__ . '/../../Database/phinx.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dbMigrateCommand = 'db:migrate';
        $configFile = $input->getOption('config');

        $output->writeln("Running Phinx command: $dbMigrateCommand with config: $configFile");
        
        try {
            $phinxApp = new PhinxApplication();
            $phinxApp->setAutoExit(false);
            
            $commands = array_merge(['./vendor/bin/phinx', 'migrate', '-c', $configFile]);
            
            $phinxApp->run(new \Symfony\Component\Console\Input\ArgvInput($commands));
        
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
