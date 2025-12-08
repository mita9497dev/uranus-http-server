<?php 
namespace Mita\UranusHttpServer\Routes;

use Slim\App;

class Route implements RouteInterface
{
    public function register(App $app)
    {
        $app->options('/{routes:.*}', function ($request, $response) {
            return $response;
        });
    }
}