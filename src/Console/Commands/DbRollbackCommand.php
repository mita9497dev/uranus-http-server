<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Configs\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Phinx\Console\PhinxApplication;

class DbRollbackCommand extends Command
{
    protected static $defaultName = 'db:rollback';
    protected $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Rollback the last migration')
            ->setHelp('This command allows you to rollback the last migration')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'The path to the phinx.php configuration file', __DIR__ . '/../../Database/phinx.php')
            ->addOption('target', 't', InputOption::VALUE_OPTIONAL, 'The version number to rollback to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dbRollbackCommand = 'db:rollback';
        $configFile = $input->getOption('config');
        $targetVersion = $input->getOption('target');
        
        $output->writeln("Running Phinx command: $dbRollbackCommand with config: $configFile");
        
        $phinxApp = new PhinxApplication();
        $phinxApp->setAutoExit(false);
        
        $commands = ['./vendor/bin/phinx', 'rollback', '-c', $configFile];
        
        if ($targetVersion) {
            $commands[] = '-t';
            $commands[] = $targetVersion;
        }
        
        $phinxApp->run(new \Symfony\Component\Console\Input\ArgvInput($commands));
        
        return Command::SUCCESS;
    }
}