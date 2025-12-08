<?php 
namespace Mita\UranusHttpServer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Mita\UranusHttpServer\Configs\Config;

class MakeCommandCommand extends Command
{
    protected static $defaultName = 'make:command';

    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new command class')
            ->setHelp('This command allows you to create a new command class')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the command class')
            ->addOption('namespace', 'ns', InputOption::VALUE_OPTIONAL, 'The namespace of the command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commandPath = $this->config->get('command.path');

        if (!is_dir($commandPath)) {
            $output->writeln("<error>Command path $commandPath does not exist!</error>");
            return Command::FAILURE;
        }

        $projectRoot = getcwd();
        $name = $input->getArgument('name');
        $namespace = $input->getOption('namespace') ?? $this->getProjectNamespace($projectRoot) . '\\Console\\Commands';

        $className = $this->generateClassName($name);
        $filePath = $commandPath . '/' . $className . '.php';

        if (file_exists($filePath)) {
            $output->writeln("<error>Command `$name` with class name `$className` already exists!</error>");
            return Command::FAILURE;
        }

        $this->createCommandFile($name, $namespace, $className, $filePath);
        $output->writeln("<info>Command $className created successfully at $filePath.</info>");

        return Command::SUCCESS;
    }

    private function getProjectNamespace(string $projectRoot): string
    {
        $composerJsonPath = $projectRoot . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            return 'PROJECT_NAMESPACE';
        }

        $composerData = json_decode(file_get_contents($composerJsonPath), true);
        if (isset($composerData['autoload']['psr-4'])) {
            $namespaces = array_keys($composerData['autoload']['psr-4']);
            return rtrim($namespaces[0], '\\');
        }

        return 'PROJECT_NAMESPACE';
    }

    private function generateClassName(string $name): string
    {
        if (strpos($name, ':') !== false) {
            $fragments = explode(':', $name);
            return ucfirst($fragments[0]) . ucfirst($fragments[1]) . 'Command';
        } else {
            return ucfirst($name) . 'Command';
        }
    }

    private function createCommandFile(string $name, string $namespace, string $className, string $filePath): void
    {
        $template = <<<EOD
<?php

namespace $namespace;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class $className extends Command
{
    protected static \$defaultName = '$name';

    protected function configure(): void
    {
        \$this
            ->setDescription('Describe the purpose of this command')
            ->setHelp('This command allows you to ...');
    }

    protected function execute(InputInterface \$input, OutputInterface \$output): int
    {
        // Your command logic here
        \$output->writeln('Command $className executed successfully.');

        return Command::SUCCESS;
    }
}

EOD;

        file_put_contents($filePath, $template);
    }
}
