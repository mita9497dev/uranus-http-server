<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Slim\Interfaces\RouteCollectorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RouteListCommand extends Command
{
    protected static $defaultName = 'route:list';

    private RouteCollectorInterface $routeCollector;

    public function __construct(RouteCollectorInterface $routeCollector)
    {
        print_r($routeCollector->getRoutes());
        parent::__construct();
        $this->routeCollector = $routeCollector;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List all routes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Your command logic here
        $routes = $this->routeCollector;
        $output->writeln('List of routes:');
        foreach ($routes as $route) {
            $output->writeln($route->getPattern());
        }

        return Command::SUCCESS;
    }
}
