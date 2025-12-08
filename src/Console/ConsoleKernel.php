<?php
namespace Mita\UranusHttpServer\Console;

use Mita\UranusHttpServer\Configs\Config;
use Mita\UranusHttpServer\Console\Commands\DbCreateCommand;
use Mita\UranusHttpServer\Console\Commands\DbMigrateCommand;
use Mita\UranusHttpServer\Console\Commands\DbRollbackCommand;
use Mita\UranusHttpServer\Console\Commands\DbUpdateCommand;
use Mita\UranusHttpServer\Console\Commands\DocsGenerateCommand;
use Mita\UranusHttpServer\Console\Commands\MakeActionCommand;
use Mita\UranusHttpServer\Console\Commands\MakeCommandCommand;
use Mita\UranusHttpServer\Console\Commands\MakeDocsCommand;
use Mita\UranusHttpServer\Console\Commands\MakeJobCommand;
use Mita\UranusHttpServer\Console\Commands\MakeModelCommand;
use Mita\UranusHttpServer\Console\Commands\MakeRepositoryCommand;
use Mita\UranusHttpServer\Console\Commands\MakeTaskCommand;
use Mita\UranusHttpServer\Console\Commands\MakeTransformerCommand;
use Mita\UranusHttpServer\Console\Commands\MakeUranusCommand;
use Mita\UranusHttpServer\Console\Commands\RulesListCommand;
use Mita\UranusHttpServer\Console\Commands\SeedCreateCommand;
use Mita\UranusHttpServer\Console\Commands\SeedRunCommand;
use Mita\UranusHttpServer\Console\Commands\StartJobCommand;
use Mita\UranusHttpServer\Console\Commands\StartProjectCommand;
use Mita\UranusHttpServer\Console\Commands\StartTaskCommand;
use Mita\UranusHttpServer\Console\Commands\TestCreateCommand;
use Mita\UranusHttpServer\Console\Commands\TestRunCommand;
use Mita\UranusHttpServer\Contracts\WorkableRegistry;
use Mita\UranusHttpServer\Queue\AsyncJobManager;
use Mita\UranusHttpServer\Queue\QueueInterface;
use Mita\UranusHttpServer\Queue\WorkerManager;
use Mita\UranusHttpServer\Services\LoggerService;
use Psr\Container\ContainerInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

class ConsoleKernel
{
    protected Application $application;

    protected ContainerInterface $container;

    protected Config $config;

    public function __construct(ContainerInterface $container)
    {
        $this->application = new Application('Uranus Http Server Console', '1.0');
        
        $this->container = $container;

        /* @var Config $config */
        $this->config = $this->container->get(Config::class);
    }

    public function addCommand(Command $command): void
    {
        $this->application->add($command);
    }

    public function registerCommands(): void 
    {
        $this->addCommand(new MakeCommandCommand($this->config));
        $this->addCommand(new MakeModelCommand($this->config));
        $this->addCommand(new MakeDocsCommand($this->config));
        $this->addCommand(new DbMigrateCommand($this->config));
        $this->addCommand(new DbRollbackCommand($this->config));
        $this->addCommand(new DbCreateCommand($this->config));
        $this->addCommand(new DbUpdateCommand($this->config));
        $this->addCommand(new SeedCreateCommand($this->config));
        $this->addCommand(new SeedRunCommand($this->config));
        $this->addCommand(new DocsGenerateCommand($this->config));
        $this->addCommand(new StartProjectCommand($this->config));
        $this->addCommand(new MakeUranusCommand());
        $this->addCommand(new RulesListCommand());
        $this->addCommand(new MakeRepositoryCommand());
        $this->addCommand(new MakeTransformerCommand($this->config));
        $this->addCommand(new TestCreateCommand($this->config));
        $this->addCommand(new TestRunCommand());
        $this->addCommand(new MakeJobCommand($this->container->get(QueueInterface::class)));

        $logger = $this->container->get(LoggerService::class);
        $logger = $logger
                    ->addFileHandler('job.log')
                    ->createInstance('JOB_COMMAND');
        $this->addCommand(new StartJobCommand(
            $this->container->get(WorkerManager::class), 
            $this->container->get(AsyncJobManager::class), 
            $this->container->get(WorkableRegistry::class),
            $logger,
            $this->container, 
            $this->container->get(LoopInterface::class)
        ));

        $this->addCommand(new MakeTaskCommand());
        $this->addCommand(new StartTaskCommand(
            $this->container->get(WorkableRegistry::class), 
            $this->container
        ));

        $this->addCommand(new MakeActionCommand());
    }

    public function run(): void
    {
        $this->application->run();
    }
}
