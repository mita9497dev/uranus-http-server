<?php 
namespace Mita\UranusHttpServer\Database;

use DI\ContainerBuilder;
use Illuminate\Database\DatabaseServiceProvider;
use InvalidArgumentException;
use Mita\UranusHttpServer\Configs\Config;
use Mita\UranusHttpServer\Services\ServiceProvider;

try {
    $baseDirs = [
        getcwd(),
        __DIR__, 
    ];

    $autoloadFile = null;
    foreach ($baseDirs as $baseDir) {
        $potentialAutoloadPaths = [
            $baseDir . '/../../vendor/autoload.php',
            $baseDir . '/../vendor/autoload.php',
            $baseDir . '/vendor/autoload.php'
        ];
        
        foreach ($potentialAutoloadPaths as $path) {
            if (file_exists($path)) {
                $autoloadFile = $path;
                break 2;
            }
        }
    }

    if ($autoloadFile === null) {
        throw new InvalidArgumentException("Autoload file not found");
    }

    require $autoloadFile;

    foreach ($baseDirs as $baseDir) {
        if (file_exists($baseDir . '/src/Configs/DefaultConfig.php')) {
            $config = require $baseDir . '/src/Configs/DefaultConfig.php';
            break;
        } else if (file_exists($baseDir . '/src/Configs/Config.php')) {
            $config = require $baseDir . '/src/Configs/Config.php';
            break;
        } 
    }
    
    if (!defined('ROOT_DIR')) {
        define('ROOT_DIR', $baseDir);
    }
} catch (\Throwable $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

$containerBuilder = new ContainerBuilder();
$serviceProvider = new ServiceProvider();
$serviceProvider->register($containerBuilder, new Config($config));
$container = $containerBuilder->build();
$container->make(DatabaseServiceProvider::class);

/** @var Config $config */
$config = $container->get(Config::class);

$connectionName = getenv('DB_CONNECTION') ?: 'default';
$settings = $connectionName == 'default' 
    ? $config->get('database.default') 
    : $config->get("database.connections.$connectionName");

$connections = $config->get('database.connections');

if ($settings === null) {
    throw new \InvalidArgumentException("Database connection $connectionName not found.");
}

$migrationPath = $config->get('migration.migrate_path');
$seederPath = $config->get('migration.seeder_path');

$phinxConfig = [
    'paths' => [
        'migrations'    => $migrationPath,
        'seeds'         => $seederPath
    ],
    'migration_base_class' => 'Mita\UranusHttpServer\Database\Migrations\BaseMigration',
    'seed_base_class' => 'Mita\UranusHttpServer\Database\Seeds\BaseSeeder',
    'templates' => [
        'class' => 'Mita\UranusHttpServer\Database\Generators\TemplateGenerator',
    ],
    'aliases' => [
        'create' => 'Mita\UranusHttpServer\Database\Generators\CreateTableTemplateGenerator', 
        'update' => 'Mita\UranusHttpServer\Database\Generators\UpdateTableTemplateGenerator'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'production' => [
            'adapter'   => $settings['driver'],
            'host'      => $settings['host'],
            'name'      => $settings['database'],
            'user'      => $settings['username'],
            'pass'      => $settings['password'],
            'port'      => $settings['port'],
            'charset'   => $settings['charset'],
            'collation' => $settings['collation']
        ],
        'development' => [
            'adapter'   => $settings['driver'],
            'host'      => $settings['host'],
            'name'      => $settings['database'],
            'user'      => $settings['username'],
            'pass'      => $settings['password'],
            'port'      => $settings['port'],
            'charset'   => $settings['charset'],
            'collation' => $settings['collation']
        ],
        'testing' => [
            'name'       => $settings['database'],
            'connection' => $container->get(DatabaseServiceProvider::class)->getConnection()->getPdo()
        ]
    ]
];

foreach ($connections as $name => $connection) {
    $phinxConfig['environments'][$name] = [
        'adapter'   => $connection['driver'],
        'host'      => $connection['host'],
        'name'      => $connection['database'],
        'user'      => $connection['username'],
        'pass'      => $connection['password'],
        'port'      => $connection['port'],
        'charset'   => $connection['charset'],
        'collation' => $connection['collation'], 
        'prefix'    => $connection['prefix']
    ];
}

return $phinxConfig;