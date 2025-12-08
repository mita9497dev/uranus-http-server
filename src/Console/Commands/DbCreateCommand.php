<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Configs\Config;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class DbCreateCommand extends Command
{
    protected static $defaultName = 'db:create';
    protected $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new migration file')
            ->setHelp('This command allows you to create a new migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration')
            ->addArgument('alias', InputArgument::OPTIONAL, 'The alias of the migration', 'create')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'The path to the phinx.php configuration file', __DIR__ . '/../../Database/phinx.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $helper = $this->getHelper('question');
            $connections = ['default' => 'default'];
            $connections = array_merge($connections, array_keys($this->config->get('database.connections', [])));
            
            $question = new ChoiceQuestion(
                'Vui lòng chọn kết nối cơ sở dữ liệu để sử dụng',
                $connections,
                'default'
            );
            $question->setErrorMessage('Kết nối %s không hợp lệ.');

            $connection = $helper->ask($input, $output, $question) ?: 'default';

            $migrationName = $input->getArgument('name');
            $alias = $input->getArgument('alias');
            $configFile = $input->getOption('config');
            
            $output->writeln("Creating migration: $migrationName with config: $configFile using connection: $connection");
            
            $phinxApp = new PhinxApplication();
            $phinxApp->setAutoExit(false);
            
            putenv('DB_CONNECTION=' . $connection);
            
            $commands = ['./vendor/bin/phinx', 'create', $migrationName];
            if ($alias) {
                $commands[] = '--class';
                $commands[] = $alias;
            }
            $commands[] = '-c';
            $commands[] = $configFile;
            
            $phinxApp->run(new \Symfony\Component\Console\Input\ArgvInput($commands));
        
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
