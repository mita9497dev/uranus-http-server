<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Configs\Config;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Str;

class SeedCreateCommand extends Command
{
    protected static $defaultName = 'seed:create';
    protected $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new seed file')
            ->setHelp('This command allows you to create a new seed file')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the seed')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'The path to the phinx.php configuration file', __DIR__ . '/../../Database/phinx.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $seedName = $input->getArgument('name');
        $configFile = $input->getOption('config');

        $seedName = Str::studly($seedName);
        if (!Str::endsWith($seedName, 'Seeder')) {
            $seedName .= 'Seeder';
        }
        
        $output->writeln("Creating seed: $seedName with config: $configFile");
        
        $phinxApp = new PhinxApplication();
        $phinxApp->setAutoExit(false);
        
        $commands = ['./vendor/bin/phinx', 'seed:create', $seedName, '-c', $configFile];
        
        $phinxApp->run(new \Symfony\Component\Console\Input\ArgvInput($commands));
        
        return Command::SUCCESS;
    }
}
