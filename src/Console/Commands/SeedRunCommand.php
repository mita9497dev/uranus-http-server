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

class SeedRunCommand extends Command
{
    protected static $defaultName = 'seed:run';
    protected $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run a seed file')
            ->setHelp('This command allows you to run a seed file')
            ->addOption('seed', 's', InputOption::VALUE_OPTIONAL, 'The name of the seed')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'The path to the phinx.php configuration file', __DIR__ . '/../../Database/phinx.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $input->getOption('config');
        $seed = $input->getOption('seed');
        $seed = Str::studly($seed);
        if (!Str::endsWith($seed, 'Seeder')) {
            $seed = $seed . 'Seeder';
        }
   
        $output->writeln("Running seed with config: $configFile");
        
        $phinxApp = new PhinxApplication();
        $phinxApp->setAutoExit(false);

        if ($seed) {
            $commands = ['./vendor/bin/phinx', 'seed:run', '-c', $configFile, '--seed', $seed];
        } else {
            $commands = ['./vendor/bin/phinx', 'seed:run', '-c', $configFile];
        }
        
        $phinxApp->run(new \Symfony\Component\Console\Input\ArgvInput($commands));
        
        return Command::SUCCESS;
    }
}
