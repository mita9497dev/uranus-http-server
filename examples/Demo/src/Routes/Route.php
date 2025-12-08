<?php 
namespace Mita\HttpServerDemo\Routes;

use Mita\HttpServerDemo\Actions\Routers\ReleaseMACAction;
use Mita\UranusHttpServer\Routes\RouteInterface;
use Slim\App;

class Route implements RouteInterface
{
    public function register(App $app)
    {
        $app->get('/release-mac', ReleaseMACAction::class);
        $app->get('/register-router', ReleaseMACAction::class);
    }
}
