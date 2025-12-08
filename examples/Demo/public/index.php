<?php 
namespace Mita\HttpServerDemo;

use Mita\UranusHttpServer\Server;

use Mita\HttpServerDemo\Routes\Route;

require __DIR__ . '/../vendor/autoload.php';

define('ROOT_DIR', dirname(__DIR__, 1));

$config = require __DIR__ . '/../src/Configs/Config.php';
$server = new Server($config);

$server->registerDefaultMiddlewares();
$server->registerDefaultRoutes();

$route = new Route();
$server->registerRoute($route);

$server->run();
