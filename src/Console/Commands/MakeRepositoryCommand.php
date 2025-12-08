<?php 
namespace Mita\UranusHttpServer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Str;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MakeRepositoryCommand extends Command
{
    protected static $defaultName = 'make:repository';

    protected function configure(): void
    {
        $this->setDescription('Create a new repository class')
            ->setHelp('This command allows you to create a new repository class in the user\'s src/Repositories directory.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the repository class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $nameRepository = $input->getArgument('name');
        $nameRepository = Str::studly($nameRepository);

        if (!Str::endsWith($nameRepository, 'Repository')) {
            $nameRepository .= 'Repository';
        }

        if (Str::contains($nameRepository, '\\')) {
            $output->writeln("<info><fg=red>Repository name cannot contain namespace separator</></info>");
            return Command::FAILURE;
        }

        $projectRoot = getcwd();
        $namespace = $this->getProjectNamespace($projectRoot) . '\Repositories';

        $repositoryPath = $projectRoot . '/src/Repositories/' . $nameRepository . '.php';

        if (file_exists($repositoryPath)) {
            $output->writeln("<info><fg=red>Repository $nameRepository already exists</></info>");
            return Command::FAILURE;
        }

        // hiển thị danh sách model
        $modelNamespace = $this->getProjectNamespace($projectRoot) . '\Models';
        $files = scandir($projectRoot . '/src/Models');
        $models = [];
        foreach ($files as $file) {
            if (is_file($projectRoot . '/src/Models/' . $file)) {
                $models[] = $file;
            }
        }

        // ask user to choose a model
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion('Please choose a model: ', $models);
        $question->setErrorMessage('Model %s is invalid.');
        $className = $helper->ask($input, $output, $question);
        $className = str_replace('.php', '', $className);

        $repositoryContent = <<<PHP
<?php
namespace $namespace;

use Mita\UranusHttpServer\Repositories\AbstractRepository;
use $modelNamespace\\$className;

class $nameRepository extends AbstractRepository
{
    public function __construct($className \$model)
    {
        parent::__construct(\$model);
    }

    public function getModel(): string
    {
        return $className::class;
    }
}
PHP;

        file_put_contents($repositoryPath, $repositoryContent);
        $output->writeln("<info><fg=green>Created $repositoryPath</></info>");
        
        // Thêm thông báo nhắc nhở
        $output->writeln("\n<comment>Remember to register your repository in RepositoryCollection:</comment>");
        $output->writeln("\n<info>Add this line to src/Repositories/RepositoryCollection.php:</info>");
        $output->writeln("<fg=yellow>{$nameRepository}::class,</>");
        $output->writeln("\nExample:");
        $output->writeln(<<<EOT
<fg=blue>
class RepositoryCollection implements RepositoryCollectionInterface
{
    public function getRepositories(): array
    {
        return [
            // ... other repositories
            {$nameRepository}::class,  // <-- Add this line
        ];
    }
}
</></fg=blue>
EOT
        );

        return Command::SUCCESS;
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

    private function getProjectNamespace(string $projectRoot): string
    {
        $namespace = 'App';
        $projectRoot = $this->getProjectRoot();
        $composerJson = $projectRoot . '/composer.json';
        if (file_exists($composerJson)) {
            $composerConfig = json_decode(file_get_contents($composerJson), true);
            if (isset($composerConfig['name']) && $composerConfig['name'] === 'mita/uranus-http-server') {
                $namespace = 'Mita\\UranusHttpServer';
            } elseif (isset($composerConfig['autoload']['psr-4'])) {
                $psr4 = array_keys($composerConfig['autoload']['psr-4'])[0];
                $namespace = rtrim($psr4, '\\') . '';
            }
        }
        return $namespace;
    }
}
