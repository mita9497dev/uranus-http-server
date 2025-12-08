<?php 
namespace Mita\UranusHttpServer\Services;

use Psr\Container\ContainerInterface;
use Mita\UranusHttpServer\Extensions\TwigExtensionRegistrarInterface;
use Slim\Views\Twig;

class TwigExtensionManager
{
    protected ContainerInterface $container;
    protected array $registrars;

    public function __construct(ContainerInterface $container, array $registrars = [])
    {
        $this->container = $container;
        $this->registrars = $registrars;
    }

    public function addExtension(string $registrarClass): void
    {
        $this->registrars[] = $registrarClass;
    }

    public function registerExtensions(Twig $twig): void
    {
        foreach ($this->registrars as $registrarClass) {
            if (class_exists($registrarClass) === false) {
                throw new \InvalidArgumentException("Class $registrarClass does not exist");
            }
            
            $registrar = $this->container->get($registrarClass);
            if ($registrar instanceof TwigExtensionRegistrarInterface) {
                $registrar->register($twig);
            }
        }
    }
}
