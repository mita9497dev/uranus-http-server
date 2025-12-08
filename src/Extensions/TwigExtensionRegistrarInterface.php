<?php 
namespace Mita\UranusHttpServer\Extensions;

use Slim\Views\Twig;

interface TwigExtensionRegistrarInterface
{
    public function register(Twig $twig): void;
}
