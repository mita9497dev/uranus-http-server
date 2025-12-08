<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeTaskCommand extends Command
{
    protected static $defaultName = 'make:task';

    protected function configure(): void
    {
        $this->setDescription('Create a new task class')
            ->addArgument('task_name', InputArgument::REQUIRED, 'The name of the task to create');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $taskName = $input->getArgument('task_name');

        $taskClass = $this->createTaskClass($taskName);

        $output->writeln("<info>Task class $taskClass created successfully.</info>");

        if ($this->hasMultipleDependencies($taskClass)) {
            $output->writeln("\n<comment>Note:</comment> Your task has multiple dependencies.");
            $output->writeln("<info>Ensure to register it in src/Services/ServiceProvider.php:</info>");
            $output->writeln("<fg=yellow>{$taskName}::class => function(ContainerInterface \$container) { /* ... */ },</>");
        }

        return Command::SUCCESS;
    }

    private function createTaskClass(string $taskName): string
    {
        $taskClass = $taskName . 'Task';
        $taskClassPath = $this->getProjectRoot() . '/src/Tasks/' . $taskClass . '.php';

        if (file_exists($taskClassPath)) {
            throw new \Exception("Task class $taskClass already exists.");
        }

        $namespace = 'App\\Tasks';
        $projectRoot = $this->getProjectRoot();
        $composerJson = $projectRoot . '/composer.json';
        if (file_exists($composerJson)) {
            $composerConfig = json_decode(file_get_contents($composerJson), true);
            if (isset($composerConfig['name']) && $composerConfig['name'] === 'mita/uranus-http-server') {
                $namespace = 'Mita\\UranusHttpServer\\Tasks';
            } elseif (isset($composerConfig['autoload']['psr-4'])) {
                $psr4 = array_keys($composerConfig['autoload']['psr-4'])[0];
                $namespace = rtrim($psr4, '\\') . '\\Tasks';
            }
        }

        $taskContent = <<<EOT
<?php
namespace $namespace;

use Mita\UranusHttpServer\Contracts\AbstractWorkable;
use Mita\UranusHttpServer\Contracts\WorkableInterface;

class $taskClass extends AbstractWorkable implements WorkableInterface
{
    public function execute(): void
    {
        echo "Running $taskClass\\n";
    }
}
EOT;

        file_put_contents($taskClassPath, $taskContent);

        return $taskClass;
    }

    private function getProjectRoot(): string
    {
        $dir = getcwd();
        while (!file_exists("$dir/vendor")) {
            $dir = dirname($dir);
            if ($dir === '/') {
                throw new \Exception('Project root not found');
            }
        }
        return $dir;
    }

    private function hasMultipleDependencies(string $taskTemplate): bool
    {
        return substr_count($taskTemplate, 'function __construct(') > 0 && 
               substr_count($taskTemplate, ',') > 0;
    }
}
