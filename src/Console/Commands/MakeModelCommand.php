<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Str;
use Mita\UranusHttpServer\Configs\Config;
use Symfony\Component\Console\Question\ChoiceQuestion;

class MakeModelCommand extends Command
{
    protected static $defaultName = 'make:model';

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
        $modelName = $input->getArgument('name');
        $helper = $this->getHelper('question');

        // Chọn database connection
        $dbConnections = $this->config->get('database.connections', []);
        $connections = ['default'];
        foreach ($dbConnections as $key => $value) {
            $connections[] = $key;
        }
        
        $connectionQuestion = new ChoiceQuestion(
            'Please select the database connection to use',
            $connections,
            0  // default là lựa chọn đầu tiên
        );
        $connectionQuestion->setErrorMessage('Connection %s is invalid.');
        $connectionName = $helper->ask($input, $output, $connectionQuestion);

        // Thêm lựa chọn loại model
        $modelTypes = [
            'Regular Model',
            'Storable Model (for file/image handling)'
        ];
        
        $typeQuestion = new ChoiceQuestion(
            'Please select the model type',
            $modelTypes,
            0  // default là lựa chọn đầu tiên
        );
        $typeQuestion->setErrorMessage('Model type %s is invalid.');
        $selectedType = $helper->ask($input, $output, $typeQuestion);
        
        // Chuyển đổi lựa chọn thành type
        $modelType = $selectedType === 'Storable Model (for file/image handling)' ? 'storable' : 'regular';

        $tableName = Str::snake(Str::pluralStudly($modelName));
        $namespace = $this->getNamespace();

        // Tạo template dựa trên loại model
        if ($modelType === 'storable') {
            $modelTemplate = $this->getStorableModelTemplate($modelName, $namespace, $connectionName, $tableName);
        } else {
            $modelTemplate = $this->getRegularModelTemplate($modelName, $namespace, $connectionName, $tableName);
        }

        $projectRoot = getcwd();
        $modelDirectory = $projectRoot . '/src/Models';

        if (!is_dir($modelDirectory)) {
            mkdir($modelDirectory, 0777, true);
            $output->writeln("Created directory: $modelDirectory");
        }

        $modelFilePath = $modelDirectory . '/' . $modelName . '.php';
        if (file_exists($modelFilePath)) {
            $output->writeln("<error>Model $modelName already exists!</error>");
            return Command::FAILURE;
        }

        file_put_contents($modelFilePath, $modelTemplate);
        $output->writeln("<info>Model $modelName created successfully in $modelFilePath</info>");

        return Command::SUCCESS;
    }

    private function getStorableModelTemplate(string $modelName, string $namespace, string $connectionName, string $tableName): string
    {
        return <<<EOT
<?php

namespace $namespace;

use Illuminate\Database\Eloquent\Model;
use Mita\UranusHttpServer\Contracts\StorableInterface;
use Mita\UranusHttpServer\Traits\HasStorageTrait;

class $modelName extends Model implements StorableInterface
{
    use HasStorageTrait;

    protected \$connection = '$connectionName';
    protected \$table = '$tableName';

    protected \$fillable = [
        'name',
        'path',
        'mime_type',
        'size',
        'type',
        'reference_id',
        'reference_type',
        'metadata',
        'sort_order'
    ];

    protected \$casts = [
        'metadata' => 'array',
        'size' => 'integer',
        'sort_order' => 'integer'
    ];

    // TODO: Change storage path
    protected \$storagePath = 'uploads/{$tableName}';
    protected \$visibility = 'public';
    protected \$validationRules = [
        'max_size' => 10, // 10MB
        'mime_types' => [
            // TODO: Add more mime types here
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif'
        ],
        'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'gif']
    ];

    public function afterStore(string \$path): void
    {
        \$metadata = \$this->getFileMetadata(\$path);
        
        \$this->update([
            'path' => \$path,
            'mime_type' => \$metadata['mime_type'] ?? null,
            'size' => \$metadata['size'] ?? null,
            'metadata' => \$metadata
        ]);
    }

    public function getUrl(): ?string 
    {
        return \$this->path ? \$this->getFileUrl(\$this->path) : null;
    }

    public function beforeDelete(): void
    {
        if (\$this->path) {
            \$this->deleteFile(\$this->path);
        }
    }
}
EOT;
    }

    private function getRegularModelTemplate(string $modelName, string $namespace, string $connectionName, string $tableName): string
    {
        return <<<EOT
<?php

namespace $namespace;

use Illuminate\Database\Eloquent\Model;

class $modelName extends Model
{
    protected \$connection = '$connectionName';
    protected \$table = '$tableName';

    protected \$fillable = [];

    protected \$hidden = [];

    protected \$casts = [];
}
EOT;
    }

    private function getNamespace(): string
    {
        $projectRoot = $this->getProjectRoot();
        $composerJson = $projectRoot . '/composer.json';
        if (file_exists($composerJson)) {
            $composerConfig = json_decode(file_get_contents($composerJson), true);
            if (isset($composerConfig['name']) && $composerConfig['name'] === 'mita/uranus-http-server') {
                return 'Mita\\UranusHttpServer\\Models';
            } elseif (isset($composerConfig['autoload']['psr-4'])) {
                $psr4 = array_keys($composerConfig['autoload']['psr-4'])[0];
                return rtrim($psr4, '\\') . '\\Models';
            }
        }
        return 'App\\Models';
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
}
