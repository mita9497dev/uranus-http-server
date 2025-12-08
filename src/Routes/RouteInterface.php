<?php 
namespace Mita\UranusHttpServer\Routes;

use Slim\App;

interface RouteInterface
{
    public function register(App $app);
}
