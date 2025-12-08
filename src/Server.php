<?php 

namespace Mita\UranusHttpServer;

use DI\Container;
use DI\ContainerBuilder;
use Illuminate\Database\DatabaseServiceProvider;
use Mita\UranusHttpServer\Configs\Config;
use Mita\UranusHttpServer\Console\ConsoleKernel;
use Mita\UranusHttpServer\Middlewares\CorsMiddleware;
use Mita\UranusHttpServer\Middlewares\JWTAuthMiddlewareInterface;
use Mita\UranusHttpServer\Middlewares\TrailingMiddleware;
use Mita\UranusHttpServer\Repositories\RepositoryCollectionInterface;
use Mita\UranusHttpServer\Repositories\RepositoryProvider;
use Mita\UranusHttpServer\Routes\Route;
use Mita\UranusHttpServer\Routes\RouteInterface;
use Mita\UranusHttpServer\Services\ServiceProvider;
use Mita\UranusHttpServer\Services\TwigExtensionManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use Slim\Middleware\Session;
use Symfony\Component\Console\Command\Command;

class Server
{
    protected Container $container;

    protected App $app;

    protected ServiceProvider $serviceProvider;

    protected RepositoryProvider $repositoryProvider;

    public function __construct($userConfig = [], $userDefinedServices = [])
    {
        $defaultConfig = require __DIR__ . '/Configs/DefaultConfig.php';
        
        $config = new Config($defaultConfig, $userConfig);

        $containerBuilder = new ContainerBuilder();
        // cache
        if ($config->get('app.env') === 'production') {
            $containerBuilder->enableDefinitionCache();
        }
        $this->serviceProvider = new ServiceProvider();
        $this->serviceProvider->register($containerBuilder, $config, $userDefinedServices);

        
        $this->repositoryProvider = new RepositoryProvider();
        $this->repositoryProvider->register($containerBuilder);
        
        $this->container = $containerBuilder->build();
        $this->app = $this->container->get(App::class);
        
        if ($config->get('app.debug')) {
            $this->registerDebugHandler();
        }
    }

    public function boot()
    {
        $this->container->make(DatabaseServiceProvider::class);
    }

    public function run()
    {
        $this->app->run();
    }

    public function registerDefaultRoutes() 
    {
        $route = new Route();
        $this->registerRoute($route);
    }
    
    public function registerRoute(RouteInterface $route)
    {
        $route->register($this->app);
    }

    public function registerDefaultMiddlewares()
    {
        $this->registerMiddleware($this->container->get(ErrorMiddleware::class));
        if ($this->getConfig()->get('session.enabled')) {
            $this->app->add($this->container->get(Session::class));
        }
        $this->registerMiddleware($this->container->get(CorsMiddleware::class));

        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();

        $this->registerMiddleware($this->container->get(TrailingMiddleware::class));
    }
    
    public function registerMiddleware(MiddlewareInterface $middleware)
    {
        $this->app->add($middleware);
    }

    public function registerDebugHandler()
    {
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();
    }

    public function registerTwigExtension(string $registrarClass)
    {
        $this->container->get(TwigExtensionManager::class)->addExtension($registrarClass);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getConfig(): Config
    {
        return $this->container->get(Config::class);
    }

    public function registerCommand(Command $command)
    {
        $this->getConsoleKernel()->addCommand($command);
    }

    public function registerUserCommand(string $commandClass)
    {
        if (!class_exists($commandClass)) {
            throw new \InvalidArgumentException("Command class $commandClass not found");
        }

        $this->getConsoleKernel()->addCommand($this->container->get($commandClass));
    }

    public function getConsoleKernel(): ConsoleKernel
    {
        return $this->container->get(ConsoleKernel::class);
    }

    public function runConsole()
    {
        $this->getConsoleKernel()->run();
    }

    public function registerGuard(string $name, string $payloadClass)
    {
        $this->container->get(JWTAuthMiddlewareInterface::class)->addGuard($name, $payloadClass);
    }

    public function registerRepositories(RepositoryCollectionInterface $collection)
    {
        foreach ($collection->getRepositories() as $repository) {
            $this->repositoryProvider->addRepository($repository);
        }
    }
}