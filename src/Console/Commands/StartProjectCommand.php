<?php
namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Configs\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class StartProjectCommand extends Command
{
    protected static $defaultName = 'start:project';
    private $logger;
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Start the project and setup the environment')
            ->setHelp('This command copies the .env.example file to the project root as .env, checks required directories, sets permissions, and optionally starts the server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger = new ConsoleLogger($output);

        $this->checkPhpVersion($output);

        $projectRoot = getcwd();
        $envExamplePath = $this->findEnvExamplePath($projectRoot);
        $envPath = $projectRoot . '/.env';

        if (!file_exists($envExamplePath)) {
            $output->writeln("<error>.env.example file does not exist at expected locations.</error>");
            $this->logger->error(".env.example file does not exist.");
            return Command::FAILURE;
        }

        if (!file_exists($envPath)) {
            if (!copy($envExamplePath, $envPath)) {
                $output->writeln("<error>Failed to copy .env.example to .env</error>");
                $this->logger->error("Failed to copy .env.example to .env");
                return Command::FAILURE;
            }
            $output->writeln("<info>.env file created successfully in the project root directory.</info>");
            $this->logger->info(".env file created successfully.");
        } else {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>.env file already exists. Do you want to overwrite it? (y/n)</question> ', true, '/^y/i');

            if ($helper->ask($input, $output, $question)) {
                if (!copy($envExamplePath, $envPath)) {
                    $output->writeln("<error>Failed to overwrite .env file</error>");
                    $this->logger->error("Failed to overwrite .env file");
                    return Command::FAILURE;
                }
                $output->writeln("<info>.env file overwritten successfully in the project root directory.</info>");
                $this->logger->info(".env file overwritten successfully.");
            } else {
                $output->writeln("<info>Skipped overwriting .env file.</info>");
                $this->logger->info("Skipped overwriting .env file.");
            }
        }

        $this->checkAndCreateDirectories($output, $projectRoot);
        $this->checkAndSetPermissions($output, $projectRoot);
        $this->createConfigFile($input, $output, $projectRoot);
        $this->createRepositoryCollectionFile($input, $output, $projectRoot);
        $this->createRouteFile($input, $output, $projectRoot);
        $this->createBootstrapFile($input, $output, $projectRoot);
        $this->createServiceProviderFile($input, $output, $projectRoot);
        $this->createIndexFile($input, $output, $projectRoot);
        $this->displaySystemInfo($output, $envPath);

        return Command::SUCCESS;
    }

    private function findEnvExamplePath(string $projectRoot): ?string
    {
        $paths = [
            $projectRoot . '/src/.env.example', 
            __DIR__ . '/../../.env.example',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function checkAndCreateDirectories(OutputInterface $output, string $projectRoot): void
    {
        $directories = [
            $projectRoot . '/public/uploads',
            $projectRoot . '/storage/Logs',
            $projectRoot . '/storage/Cache',
            $projectRoot . '/storage/TwigCache',
            $projectRoot . '/src/Database/migrations',
            $projectRoot . '/src/Database/seeds', 
            $projectRoot . '/src/Database/factories',
            $projectRoot . '/src/Jobs',
            $projectRoot . '/src/Actions',
            $projectRoot . '/src/Configs', 
            $projectRoot . '/src/Console/Commands',
            $projectRoot . '/src/Middlewares', 
            $projectRoot . '/src/Models',
            $projectRoot . '/src/Repositories',
            $projectRoot . '/src/Routes', 
            $projectRoot . '/src/Services',
            $projectRoot . '/src/Tasks',
            $projectRoot . '/src/Translations',
            $projectRoot . '/src/Transformers',
            $projectRoot . '/src/Views',
        ];

        $filesystem = new Filesystem();

        foreach ($directories as $dir) {
            try {
                if (!$filesystem->exists($dir)) {
                    $filesystem->mkdir($dir, 0755);
                    $output->writeln("<info><fg=green>Created directory: $dir</></info>");
                    $this->logger->info("Created directory: $dir");
                }
            } catch (IOExceptionInterface $exception) {
                $output->writeln("<error>Failed to create directory: $dir</error>");
                $this->logger->error("Failed to create directory: $dir");
            }
        }
    }

    private function checkAndSetPermissions(OutputInterface $output, string $projectRoot): void
    {
        $directories = [
            $projectRoot . '/Storage'
        ];

        foreach ($directories as $dir) {
            chmod($dir, 0755);
            $output->writeln("<info><fg=green>Set permissions for directory: $dir</></info>");
            $this->logger->info("Set permissions for directory: $dir");
        }
    }

    private function createIndexFile(InputInterface $input, OutputInterface $output, string $projectRoot): void
    {
        $publicPath = $projectRoot . '/' . $this->config->get('public_path');

        if (!file_exists($publicPath)) {
            mkdir($publicPath, 0755, true);
            $output->writeln("<info><fg=green>Created directory: $publicPath</></info>");
            $this->logger->info("Created directory: $publicPath");
        }

        $indexFilePath = $publicPath . '/index.php';

        if (file_exists($indexFilePath)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>index.php already exists. Do you want to overwrite it? (y/n)</question> ', true, '/^y/i');

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("<info><fg=green>Skipped overwriting index.php.</></info>");
                $this->logger->info("Skipped overwriting index.php.");
                return;
            }
        }

        $namespace = $this->getProjectNamespace($projectRoot);

        $indexContent = <<<PHP
<?php 

\$server = require __DIR__ . '/bootstrap.php';

\$server->boot();
\$server->run();
PHP;

        file_put_contents($indexFilePath, $indexContent);
        $output->writeln("<info><fg=green>Created index.php in $publicPath</></info>");
        $this->logger->info("Created index.php in $publicPath");
    }

    private function createRouteFile(InputInterface $input, OutputInterface $output, string $projectRoot): void 
    {
        $routePath = $projectRoot . '/src/Routes';

        if (!file_exists($routePath)) {
            mkdir($routePath, 0755, true);
            $output->writeln("<info><fg=green>Created directory: $routePath</></info>");
            $this->logger->info("Created directory: $routePath");
        }

        $indexFilePath = $routePath . '/Route.php';

        if (file_exists($indexFilePath)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>Route.php already exists. Do you want to overwrite it? (y/n)</question> ', true, '/^y/i');

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("<info><fg=green>Skipped overwriting Route.php.</></info>");
                $this->logger->info("Skipped overwriting Route.php.");
                return;
            }
        }

        $namespace = $this->getProjectNamespace($projectRoot) . '\Routes';

        $indexContent = <<<PHP
<?php 
namespace $namespace;

use Mita\UranusHttpServer\Routes\RouteInterface;
use Slim\App;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Route implements RouteInterface
{
    public function register(App \$app)
    {
        \$app->get('/', function (RequestInterface \$request, ResponseInterface \$response) {
            \$response->getBody()->write('Hello, World!');
            return \$response;
        });
    }
}
PHP;

        file_put_contents($indexFilePath, $indexContent);
        $output->writeln("<info><fg=green>Created Route.php in $routePath</></info>");
        $this->logger->info("Created Route.php in $routePath");
    }

    private function createConfigFile(InputInterface $input, OutputInterface $output, string $projectRoot): void
    {
        $configDir = $projectRoot . '/src/Configs';
        $configFilePath = $configDir . '/Config.php';
        $defaultConfigPath = __DIR__ . '/../../Configs/DefaultConfig.php';

        if (file_exists($configFilePath)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>Config.php already exists. Do you want to overwrite it? (y/n)</question> ', true, '/^y/i');

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("<info><fg=green>Skipped overwriting Config.php.</></info>");
                $this->logger->info("Skipped overwriting Config.php.");
                return;
            }
        }

        $namespace = $this->getProjectNamespace($projectRoot) . '\Configs';
        if (!file_exists($configDir)) {
            mkdir($configDir, 0755, true);
        }
        $configContent = file_get_contents($defaultConfigPath);
        $configContent = preg_replace(
            '/\/\*\* NAMESPACE_REPLACE \*\//', 
            "/** NAMESPACE_REPLACE */\nnamespace $namespace", 
            $configContent
        );
        file_put_contents($configFilePath, $configContent);
        $output->writeln("<info><fg=green>Created Config.php in $configDir</></info>");
        $this->logger->info("Created Config.php in $configDir");
    }

    public function createBootstrapFile(InputInterface $input, OutputInterface $output, string $projectRoot): void
    {
        $bootstrapPath = $projectRoot . '/public/bootstrap.php';
        if (file_exists($bootstrapPath)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>bootstrap.php already exists. Do you want to overwrite it? (y/n)</question> ', true, '/^y/i');

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("<info><fg=green>Skipped overwriting bootstrap.php.</></info>");
                $this->logger->info("Skipped overwriting bootstrap.php.");
                return;
            }
        }

        $namespace = $this->getProjectNamespace($projectRoot);
        $bootstrapContent = <<<PHP
<?php 

use Mita\UranusHttpServer\Server;
use $namespace\Auth\AdminAuthPayload;
use $namespace\Repositories\RepositoryCollection;
use $namespace\Routes\Route;
use $namespace\Services\ServiceProvider;

require __DIR__ . '/../vendor/autoload.php';

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__, 1));
}

\$config = require __DIR__ . '/../src/Configs/Config.php';
\$serviceProvider = ServiceProvider::getDefinitions();
\$server = new Server(\$config, \$serviceProvider);

\$server->registerGuard('admin', AdminAuthPayload::class);
\$server->registerRepositories(new RepositoryCollection());
\$server->registerDefaultMiddlewares();
\$server->registerDefaultRoutes();

\$route = new Route();
\$server->registerRoute(\$route);

return \$server;
PHP;

        file_put_contents($bootstrapPath, $bootstrapContent);
        $output->writeln("<info><fg=green>Created bootstrap.php in $bootstrapPath</></info>");
        $this->logger->info("Created bootstrap.php in $bootstrapPath");
    }

    private function createServiceProviderFile(InputInterface $input, OutputInterface $output, string $projectRoot): void
    {
        $serviceProviderPath = $projectRoot . '/src/Services/ServiceProvider.php';
        if (file_exists($serviceProviderPath)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>ServiceProvider.php already exists. Do you want to overwrite it? (y/n)</question> ', true, '/^y/i');

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("<info><fg=green>Skipped overwriting ServiceProvider.php.</></info>");
                $this->logger->info("Skipped overwriting ServiceProvider.php.");
                return;
            }
        }

        $namespace = $this->getProjectNamespace($projectRoot) . '\Services';
        $serviceProviderContent = <<<PHP
<?php 
namespace $namespace;

class ServiceProvider
{
    public static function getDefinitions(): array
    {
        return [];
    }
}
PHP;

        file_put_contents($serviceProviderPath, $serviceProviderContent);
        $output->writeln("<info><fg=green>Created ServiceProvider.php in $serviceProviderPath</></info>");
        $this->logger->info("Created ServiceProvider.php in $serviceProviderPath");
    }

    private function createRepositoryCollectionFile(InputInterface $input, OutputInterface $output, string $projectRoot): void
    {
        $repositoryCollectionPath = $projectRoot . '/src/Repositories/RepositoryCollection.php';
        if (file_exists($repositoryCollectionPath)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>RepositoryCollection.php already exists. Do you want to overwrite it? (y/n)</question> ', true, '/^y/i');

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("<info><fg=green>Skipped overwriting RepositoryCollection.php.</></info>");
                $this->logger->info("Skipped overwriting RepositoryCollection.php.");
                return;
            }
        }

        $namespace = $this->getProjectNamespace($projectRoot) . '\Repositories';
        if (!file_exists($repositoryCollectionPath)) {
            mkdir($repositoryCollectionPath, 0755, true);
        }

        $indexContent = <<<PHP
<?php
namespace $namespace;

use Mita\UranusHttpServer\Repositories\RepositoryCollectionInterface;

class RepositoryCollection implements RepositoryCollectionInterface
{
    public function getRepositories(): array
    {
        return [];
    }
}
PHP;

        file_put_contents($repositoryCollectionPath, $indexContent);
        $output->writeln("<info><fg=green>Created RepositoryCollection.php in $repositoryCollectionPath</></info>");
        $this->logger->info("Created RepositoryCollection.php in $repositoryCollectionPath");
    }

    private function createTestCaseFile(InputInterface $input, OutputInterface $output, string $projectRoot): void
    {
        $testCasePath = $projectRoot . '/tests/TestCase.php';
        if (file_exists($testCasePath)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>TestCase.php already exists. Do you want to overwrite it? (y/n)</question> ', true, '/^y/i');

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln("<info><fg=green>Skipped overwriting TestCase.php.</></info>");
                $this->logger->info("Skipped overwriting TestCase.php.");
                return;
            }
        }

        $namespace = 'Tests';
        if (!file_exists($testCasePath)) {
            mkdir($testCasePath, 0755, true);
        }

        $testCaseContent = <<<PHP
<?php namespace Tests;

use Mita\UranusHttpServer\Contracts\AuthenticatableInterface;
use Mita\UranusHttpServer\Server;
use Mita\UranusHttpServer\Services\JWTServiceInterface;
use Mita\UranusHttpServer\Testing\BaseTestCase;
use Mockery;
use SlimSession\Helper;

class TestCase extends BaseTestCase
{
    protected function getServer(): Server
    {
        \$config = require __DIR__ . '/../src/Configs/Config.php';
        \$config['session']['enabled'] = false;
        \$server = new Server(\$config,
            [
                Helper::class => function () {
                    \$sessionHelper = Mockery::mock(Helper::class);
                    \$sessionHelper->shouldReceive('get')->andReturn(null);
                    \$sessionHelper->shouldReceive('set')->andReturn(null);
                    \$sessionHelper->shouldReceive('delete')->andReturn(null);
                    \$sessionHelper->shouldReceive('clear')->andReturn(null);
                    return \$sessionHelper;
                }
            ]);
        
        // TODO: Register guards and repositories
        // ...

        \$server->registerDefaultMiddlewares();
        \$server->registerDefaultRoutes();
        
        // TODO: Register routes
        // ...
        // \$route = new Route();
        // \$server->registerRoute(\$route);

        return \$server;
    }

    /**
     * Create test users for unit testing
     * Must be implemented in the test class
     * 
     * @return void
     */
    protected function runTestSeeder(): void
    {
        // TODO: Implement this method
    }

    protected function getUser(string \$username): AuthenticatableInterface
    {
        // TODO: Implement this method
    }

    protected function createLoginRecord(AuthenticatableInterface \$user, string \$token): void
    {
        // TODO: Implement this method
    }

    protected function generateToken(AuthenticatableInterface \$user): string
    {
        \$jwtService = \$this->container->get(JWTServiceInterface::class);
        return \$jwtService->generateToken(\$user->getAuthPayload());
    }

    protected function loginAs(string \$username): AuthenticatableInterface
    {
        \$user = \$this->getUser(\$username);
        \$token = \$this->generateToken(\$user);
        
        \$this->createLoginRecord(\$user, \$token);
        
        \$this->withToken(\$token);
        
        return \$user;
    }
}
PHP;

        file_put_contents($testCasePath, $testCaseContent);
        $output->writeln("<info><fg=green>Created TestCase.php in $testCasePath</></info>");
        $this->logger->info("Created TestCase.php in $testCasePath");
    }

    private function displaySystemInfo(OutputInterface $output, string $envPath): void
    {
        $output->writeln("<info><fg=green>System setup completed successfully.</></info>");
        $output->writeln("<info><fg=green>.env file located at: " . realpath($envPath) . "</></info>");
        $this->logger->info("System setup completed successfully.");
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

    private function checkPhpVersion(OutputInterface $output): void
    {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $output->writeln("<error>Your PHP version (" . PHP_VERSION . ") is not supported. Please upgrade to PHP 7.4 or higher.</error>");
            $this->logger->error("Unsupported PHP version: " . PHP_VERSION);

        } else {
            $output->writeln("<info>PHP version (" . PHP_VERSION . ") is supported.</info>");
            $this->logger->info("PHP version (" . PHP_VERSION . ") is supported.");
        }
    }
}
