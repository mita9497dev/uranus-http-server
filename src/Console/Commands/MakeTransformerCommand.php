<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Str;
use Mita\UranusHttpServer\Configs\Config;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MakeTransformerCommand extends Command
{
    protected static $defaultName = 'make:transformer';

    protected Config $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new Eloquent model class')
            ->setHelp('This command allows you to create a new Eloquent model class in the user\'s src/Models directory.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the model class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transformerName = $input->getArgument('name');
        $transformerName = Str::studly($transformerName);

        if (!Str::endsWith($transformerName, 'Transformer')) {
            $transformerName .= 'Transformer';
        }

        $projectRoot = getcwd();
        $transformerDirectory = $projectRoot . '/src/Transformers';

        if (!is_dir($transformerDirectory)) {
            mkdir($transformerDirectory, 0777, true);
            $output->writeln("Created directory: $transformerDirectory");
        }

        $helper = $this->getHelper('question');

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
        $modelClassName = $helper->ask($input, $output, $question);
        $modelClassName = str_replace('.php', '', $modelClassName);

        $namespace = $this->getProjectNamespace($projectRoot) . '\Transformers';

        $modelTemplate = <<<EOT
<?php

namespace $namespace;

use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;
use Mita\UranusHttpServer\Transformers\AbstractTransformer;
use $modelNamespace\\$modelClassName;

class $transformerName extends AbstractTransformer
{
    public function __construct(AuthenticatableInterface \$authenticatable, array \$defaultIncludes = [], array \$availableIncludes = [])
    {
        \$this->authenticatable = \$authenticatable;
        \$this->availableIncludes = \$availableIncludes;
        \$this->defaultIncludes = \$defaultIncludes;
    }

    /**
     * @param $modelClassName \$model
     * @return array
     */
    public function transform(\$model): array
    {
        return [];
    }
}
EOT;
        
        $modelTemplate = str_replace('$modelNamespace', $modelNamespace, $modelTemplate);
        $modelTemplate = str_replace('$modelClassName', $modelClassName, $modelTemplate);        

        $modelFilePath = $transformerDirectory . '/' . $transformerName . '.php';
        if (file_exists($modelFilePath)) {
            $output->writeln("<error>Model $transformerName already exists!</error>");
            return Command::FAILURE;
        }

        file_put_contents($modelFilePath, $modelTemplate);
        $output->writeln("<info>Model $transformerName created successfully in $modelFilePath</info>");

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
