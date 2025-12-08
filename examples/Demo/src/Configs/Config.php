<?php 
/** NAMESPACE_REPLACE */
namespace Mita\HttpServerDemo\Configs;

$basedir = defined('ROOT_DIR') ? constant('ROOT_DIR') : getcwd();

return [
    'base_path' => '',
    'base_url'  => 'http://localhost',
    'public_path' => 'public',
    'app' => [
        'env'   => 'development',
        'debug' => true,
        'name'  => 'Mita Uranus Http Server',
    ],
    'swagger' => [
        'scan_dir' => $basedir . '/src/Actions',
        'output_file' => $basedir . '/public/swagger.json',
    ],
    'locale' => [
        'default' => 'en',
        'supported' => ['en', 'vi'],
        'path' => $basedir . '/Languages',
    ],
    'renderer' => [
        'template_path' => $basedir . '/src/Views',
        'cache_enabled' => false,
        'cache_path'    => $basedir . '/storage/TwigCache',
    ],
    'router' => [
        'cache_enabled' => false,
        'cache_file'    => $basedir . '/storage/RouterCache.php',
    ],
    'jwt' => [
        'secret_key'    => 'your_secret_key',
        'algorithm'     => 'HS256',
        'access_token'  => [
            'expires_at' => '+1 hour'
        ],
        'refresh_token' => [
            'expires_at' => '+7 days'
        ],
    ],
    'auth' => [
        'header_key'    => 'Authorization', 
        'query_key'     => 'access_token'
    ],
    'command' => [
        'path' => $basedir . '/src/Console/Commands',
    ],
    'database' => [
        'default' => [
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'port'      => 3306,
            'database'  => 'uranus_http_server',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => 'uranus_',
        ],
        'connections' => [],
    ],
    'migration' => [
        'migrate_path' => $basedir . '/src/Database/Migrations',
        'seeder_path'  => $basedir . '/src/Database/Seeds',
    ],
    'cache' => [
        'driver' => 'file',
        'file' => [
            'path' => $basedir . '/storage/Cache',
        ],

        'redis' => [
            'host'      => 'localhost',
            'port'      => 6379,
            'password'  => null,
        ],

        'apcu' => [
            'namespace' => 'your_app_cache',
        ]
    ], 
    'queue' => [
        'driver' => 'rabbitmq',
        'connections' => [
            'rabbitmq' => [
                'host' => 'localhost',
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
                'prefix' => 'uranus_',
            ]
        ],
        'workers' => [
            [
                'num_workers' => 1,
                'queues' => ['default']
            ]
        ],
        'jobs' => [],
    ],
    'tasks' => [], 
    'cors' => [
        'allowed_origins'   => ['*'],
        'allowed_methods'   => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers'   => ['Content-Type', 'Authorization'],
        'exposed_headers'   => [],
        'max_age'           => 0,
        'credentials'       => true,
    ],
    'csrf' => [
        'lifetime'      => '1 hour',
        'token_key'     => '_csrf_token',
        'session_key'   => '_csrf_token',
    ],
    'rate_limit' => [
        'limit'         => 60,
        'time_window'   => 60,
    ],
    'session' => [
        'name'          => 'mita_session',
        'autorefresh'   => true,
        'lifetime'      => '1 hour',
        'secure'        => false,
        'httponly'      => true,
    ],
    'logger' => [
        'name'  => 'mita',
        'path'  => $basedir . '/src/Storage/Logs/app.log',
        'level' => \Monolog\Logger::DEBUG,
        'display_error_details' => true,
        'log_error_details'     => true,
        'log_errors'            => true,
    ],
];
