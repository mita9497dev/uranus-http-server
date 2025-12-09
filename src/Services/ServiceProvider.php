<?php
namespace Mita\UranusHttpServer\Services;

use DI\ContainerBuilder;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\DatabaseServiceProvider;
use League\Fractal\Manager as FractalManager;
use Mita\UranusHttpServer\Cache\CacheManager;
use Mita\UranusHttpServer\Cache\EloquentCache;
use Mita\UranusHttpServer\Configs\Config;
use Mita\UranusHttpServer\Console\ConsoleKernel;
use Mita\UranusHttpServer\Contracts\WorkableRegistry;
use Mita\UranusHttpServer\Extensions\TwigTranslatorExtension;
use Mita\UranusHttpServer\Handlers\HttpErrorHandler;
use Mita\UranusHttpServer\Helpers\UranusHelper;
use Mita\UranusHttpServer\Middlewares\CorsMiddleware;
use Mita\UranusHttpServer\Middlewares\CsrfMiddleware;
use Mita\UranusHttpServer\Middlewares\CsrfMiddlewareInterface;
use Mita\UranusHttpServer\Middlewares\JWTAuthMiddleware;
use Mita\UranusHttpServer\Middlewares\JWTAuthMiddlewareInterface;
use Mita\UranusHttpServer\Middlewares\LocaleMiddleware;
use Mita\UranusHttpServer\Middlewares\RateLimitMiddleware;
use Mita\UranusHttpServer\Middlewares\TrailingMiddleware;
use Mita\UranusHttpServer\Middlewares\ViewGlobalVariableMiddleware;
use Mita\UranusHttpServer\Queue\QueueInterface;
use Mita\UranusHttpServer\Queue\QueueManager;
use Mita\UranusHttpServer\Queue\WorkerManager;
use Mita\UranusHttpServer\Queue\AsyncJobManager;
use React\EventLoop\LoopInterface;
use Mita\UranusHttpServer\Traits\HasStorageTrait;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use Slim\Middleware\Session;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use SlimSession\Helper;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class ServiceProvider
{
    public function register(ContainerBuilder $containerBuilder, Config $config, array $userDefinedServices = [])
    {
        $this->registerApp($containerBuilder);
        $this->registerConfig($containerBuilder, $config);
        $this->registerConsole($containerBuilder);
        $this->registerDatabase($containerBuilder);
        $this->registerCache($containerBuilder);
        $this->registerLogger($containerBuilder);
        $this->registerTwig($containerBuilder);
        $this->registerTwigExtensionManager($containerBuilder);
        $this->registerTwigTranslatorExtension($containerBuilder);
        $this->registerSessionHelper($containerBuilder);
        $this->registerValidatorService($containerBuilder);
        $this->registerTransformerService($containerBuilder);
        $this->registerGlobalVariablesService($containerBuilder);
        $this->registerTranslatorService($containerBuilder);
        $this->registerJwtService($containerBuilder);
        $this->registerErrorHandler($containerBuilder);
        $this->registerMiddleware($containerBuilder);
        $this->registerQueue($containerBuilder);
        $this->registerWorkerManager($containerBuilder);
        $this->registerFilesystem($containerBuilder);

        $containerBuilder->addDefinitions($userDefinedServices);
    }

    public function registerApp(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            \Slim\App::class => function (ContainerInterface $container) {
                $app = \Slim\Factory\AppFactory::createFromContainer($container);

                $basePath = $container->get(Config::class)->get('base_path');
                $app->setBasePath($basePath);

                UranusHelper::setContainer($container);

                return $app;
            }
        ]);
    }

    public function registerConfig(ContainerBuilder $containerBuilder, Config $config)
    {
        $containerBuilder->addDefinitions([
            Config::class => function (ContainerInterface $container) use ($config) {
                return $config;
            }
        ]);
    }

    public function registerConsole(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            ConsoleKernel::class => function (ContainerInterface $container) {
                return new ConsoleKernel($container);
            }
        ]);
    }

    public function registerDatabase(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            DatabaseServiceProvider::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $settings = $config->get('database');

                $capsule = new Manager();

                $capsule->addConnection([
                    'driver' => $settings['default']['driver'],
                    'host' => $settings['default']['host'],
                    'port' => $settings['default']['port'],
                    'database' => $settings['default']['database'],
                    'username' => $settings['default']['username'],
                    'password' => $settings['default']['password'],
                    'charset' => $settings['default']['charset'],
                    'collation' => $settings['default']['collation'],
                    'prefix' => $settings['default']['prefix'],
                ], 'default');

                foreach ($settings['connections'] as $name => $connection) {
                    $capsule->addConnection([
                        'driver' => $connection['driver'],
                        'host' => $connection['host'],
                        'port' => $connection['port'],
                        'database' => $connection['database'],
                        'username' => $connection['username'],
                        'password' => $connection['password'],
                        'charset' => $connection['charset'],
                        'collation' => $connection['collation'],
                        'prefix' => $connection['prefix'],
                    ], $name);
                }

                $capsule->setAsGlobal();

                $capsule->bootEloquent();

                return $capsule;
            }
        ]);
    }

    public function registerCache(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            CacheInterface::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $cacheConfigs = $config->get('cache');
                $cacheManager = new CacheManager($cacheConfigs);
                return $cacheManager->getCacheDriver();
            }
        ]);

        $containerBuilder->addDefinitions([
            EloquentCache::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $cacheConfigs = $config->get('cache');
                return new EloquentCache($container->get(CacheInterface::class), $cacheConfigs['ttl']);
            }
        ]);
    }

    public function registerTwig(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            Twig::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $twigConfigs = $config->get('renderer');

                $options['cache'] = $twigConfigs['cache_enabled'] ? $twigConfigs['cache_path'] : false;
                $twig = Twig::create($twigConfigs['template_path'], $options);

                /** @var TwigExtensionManager */
                $twigExtensionManager = $container->get(TwigExtensionManager::class);
                $twigExtensionManager->registerExtensions($twig);

                return $twig;
            },
        ]);
    }

    public function registerTwigExtensionManager(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            TwigExtensionManager::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $twigConfigs = $config->get('renderer');
                $registrars = $twigConfigs['extensions'] ?? [];
                $registrars[] = TwigTranslatorExtension::class;

                return new TwigExtensionManager($container, $registrars);
            },
        ]);
    }

    public function registerTwigTranslatorExtension(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            TwigTranslatorExtension::class => function (ContainerInterface $container) {
                $translator = $container->get(TranslatorInterface::class);
                return new TwigTranslatorExtension($translator);
            },
        ]);
    }

    public function registerSessionHelper(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            Helper::class => function () {
                return new Helper();
            }
        ]);
    }

    public function registerValidatorService(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            ValidatorService::class => function (ContainerInterface $container) {
                return new ValidatorService($container->get(Helper::class));
            }
        ]);
    }

    public function registerTransformerService(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            TransformerService::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                return new TransformerService(new FractalManager());
            }
        ]);
    }

    public function registerGlobalVariablesService(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            GlobalVariableService::class => function (ContainerInterface $container) {
                $twig = $container->get(Twig::class);

                return new GlobalVariableService($twig);
            },
        ]);
    }

    public function registerLogger(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            LoggerService::class => function (ContainerInterface $container) {
                return new LoggerService(
                    $container->get(Config::class)->get('logger.path'),
                    $container->get(Config::class)->get('logger.name'),
                    $container->get(Config::class)->get('logger.level')
                );
            },
        ]);
    }

    public function registerErrorHandler(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            HttpErrorHandler::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);

                $loggerService = $container->get(LoggerService::class);
                $logger = $loggerService
                    ->addFileHandler('http_error_handler.log')
                    ->createInstance('ERROR_HANDLER');

                $app = $container->get(App::class);
                $httpErrorHandler = new HttpErrorHandler(
                    $app->getCallableResolver(),
                    $app->getResponseFactory(),
                    $logger
                );
                $httpErrorHandler->setDebug(filter_var($config->get('app.debug'), FILTER_VALIDATE_BOOLEAN));

                return $httpErrorHandler;
            },
        ]);
    }

    public function registerTranslatorService(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            TranslatorInterface::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $settings = $config->get('locale');

                return new TranslatorService($settings['default'], $settings['path']);
            },
        ]);
    }

    public function registerJwtService(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions(
            [
                JWTServiceInterface::class => function (ContainerInterface $container) {
                    /** @var Config $config */
                    $config = $container->get(Config::class);
                    $baseUrl = $config->get('base_url');
                    $secretKey = $config->get('jwt.secret_key');
                    $algorithm = $config->get('jwt.algorithm');

                    $accessTokenOptions = [
                        'expires_at' => $config->get('jwt.access_token.expires_at'),
                        'issued_by' => $baseUrl,
                        'permitted_for' => $baseUrl,
                    ];

                    return new JWTService($secretKey, $algorithm, $accessTokenOptions);
                }
            ]
        );
    }

    public function registerQueue(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            QueueManager::class => function (ContainerInterface $container) {
                $loggerService = $container->get(LoggerService::class);
                $config = $container->get(Config::class);

                $logger = $loggerService
                    ->addFileHandler('queue_manager.log')
                    ->createInstance('QUEUE_MANAGER');

                return new QueueManager($config, $logger);
            }
        ]);

        $containerBuilder->addDefinitions([
            QueueInterface::class => function (ContainerInterface $container) {
                return $container->get(QueueManager::class)->getQueue();
            }
        ]);
    }

    public function registerWorkerManager(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            WorkableRegistry::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);

                $workableRegistry = new WorkableRegistry();

                $jobs = $config->get('queue.jobs', []);
                foreach ($jobs as $job) {
                    $jobClassName = "\\" . $job;
                    $workableRegistry->register($jobClassName);
                }

                $tasks = $config->get('tasks', []);
                foreach ($tasks as $task) {
                    $taskClassName = "\\" . $task;
                    $workableRegistry->register($taskClassName);
                }

                return $workableRegistry;
            }
        ]);

        $containerBuilder->addDefinitions([
            WorkerManager::class => function (ContainerInterface $container) {
                $loggerService = $container->get(LoggerService::class);

                $logger = $loggerService
                    ->addFileHandler('worker_manager.log')
                    ->createInstance('WORKER_MANAGER');

                return new WorkerManager(
                    $container->get(QueueInterface::class),
                    $logger,
                    $container->get(WorkableRegistry::class)
                );
            }
        ]);

        $containerBuilder->addDefinitions([
            AsyncJobManager::class => function (ContainerInterface $container) {
                $loggerService = $container->get(LoggerService::class);

                $logger = $loggerService
                    ->addFileHandler('async_job_manager.log')
                    ->createInstance('ASYNC_JOB_MANAGER');

                return new AsyncJobManager(
                    $container->get(QueueInterface::class),
                    $logger,
                    $container->get(WorkableRegistry::class),
                    $container->get(LoopInterface::class),
                    $container
                );
            }
        ]);

        $containerBuilder->addDefinitions([
            LoopInterface::class => function (ContainerInterface $container) {
                return \React\EventLoop\Loop::get();
            }
        ]);
    }

    public function registerMiddleware(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            Session::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $settings = $config->get('session');
                return new Session([
                    'name' => $settings['name'],
                    'autorefresh' => $settings['autorefresh'],
                    'lifetime' => $settings['lifetime'],
                    'secure' => $settings['secure'],
                    'httponly' => $settings['httponly']
                ]);
            },
        ]);

        $containerBuilder->addDefinitions([
            TrailingMiddleware::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $basePath = $config->get('base_path');

                return new TrailingMiddleware($basePath);
            },
        ]);

        $containerBuilder->addDefinitions([
            ViewGlobalVariableMiddleware::class => function (ContainerInterface $container) {
                return new ViewGlobalVariableMiddleware(
                    $container->get(ContainerInterface::class),
                    $container->get(GlobalVariableService::class)
                );
            },
        ]);

        $containerBuilder->addDefinitions([
            TwigMiddleware::class => function (ContainerInterface $container) {
                return TwigMiddleware::createFromContainer($container->get(\Slim\App::class), Twig::class);
            },
        ]);

        $containerBuilder->addDefinitions([
            ErrorMiddleware::class => function (ContainerInterface $container) {
                $config = $container->get(Config::class);
                $app = $container->get(\Slim\App::class);

                $loggerService = $container->get(LoggerService::class);
                $logger = $loggerService
                    ->addFileHandler('error_middleware.log')
                    ->createInstance('ERROR_MIDDLEWARE');

                $errorMiddleware = new ErrorMiddleware(
                    $app->getCallableResolver(),
                    $app->getResponseFactory(),
                    (bool) $config->get('logger.display_error_details'),
                    (bool) $config->get('logger.log_errors'),
                    (bool) $config->get('logger.log_error_details'),
                    $logger
                );
                $errorMiddleware->setDefaultErrorHandler($container->get(HttpErrorHandler::class));
                return $errorMiddleware;
            },
        ]);

        $containerBuilder->addDefinitions([
            CorsMiddleware::class => \DI\create(CorsMiddleware::class)
        ]);

        $containerBuilder->addDefinitions([
            CsrfMiddlewareInterface::class => function (ContainerInterface $container) {
                /** @var Config */
                $config = $container->get(Config::class);
                $lifetime = $config->get('csrf.lifetime');
                $tokenKey = $config->get('csrf.token_key');
                $sessionKey = $config->get('csrf.session_key');
                return new CsrfMiddleware(
                    $container->get(Helper::class),
                    $lifetime,
                    $tokenKey,
                    $sessionKey
                );
            },
        ]);

        $containerBuilder->addDefinitions([
            RateLimitMiddleware::class => function (ContainerInterface $container) {
                $cache = $container->get(CacheInterface::class);
                /** @var Config */
                $config = $container->get(Config::class);
                $limit = $config->get('rate_limit.limit');
                $timeWindow = $config->get('rate_limit.time_window');

                return new RateLimitMiddleware($cache, $limit, $timeWindow);
            }
        ]);

        $containerBuilder->addDefinitions([
            LocaleMiddleware::class => function (ContainerInterface $container) {
                $translator = $container->get(TranslatorInterface::class);
                $cache = $container->get(CacheInterface::class);

                return new LocaleMiddleware($translator, $cache);
            }
        ]);

        $containerBuilder->addDefinitions([
            JWTAuthMiddlewareInterface::class => function (ContainerInterface $container) {
                /** @var Config */
                $config = $container->get(Config::class);

                $middleware = new JWTAuthMiddleware($container->get(JWTServiceInterface::class));
                $middleware->setHeaderKey($config->get('auth.header_key'));
                $middleware->setQueryKey($config->get('auth.query_key'));

                return $middleware;
            }
        ]);
    }

    public function registerFilesystem(ContainerBuilder $containerBuilder)
    {
        $containerBuilder->addDefinitions([
            FilesystemOperator::class => function (ContainerInterface $container) {
                /** @var Config */
                $config = $container->get(Config::class);
                $storageConfig = $config->get('storage');
                $disk = $storageConfig['disks'][$storageConfig['default']];

                $adapter = new LocalFilesystemAdapter(
                    $disk['root'],
                    PortableVisibilityConverter::fromArray($disk['permissions'] ?? [
                        'file' => [
                            'public' => 0644,
                            'private' => 0600,
                        ],
                        'dir' => [
                            'public' => 0755,
                            'private' => 0700,
                        ],
                    ])
                );

                return new Filesystem($adapter, [
                    'visibility' => $disk['visibility'] ?? 'private'
                ]);
            }
        ]);
    }
}