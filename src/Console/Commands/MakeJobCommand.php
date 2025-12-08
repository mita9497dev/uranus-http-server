<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Queue\QueueInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeJobCommand extends Command
{
    protected static $defaultName = 'make:job';

    protected QueueInterface $queue;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Create a new job class')
            ->addArgument('job_name', InputArgument::REQUIRED, 'The name of the job to create');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobName = $input->getArgument('job_name');

        $jobClass = $this->createJobClass($jobName);

        $output->writeln("<info>Job class $jobClass created successfully in Jobs folder.</info>");

        return Command::SUCCESS;
    }

    private function createJobClass(string $jobName): string
    {
        $jobClass = $jobName . 'Job';
        $jobClassPath = $this->getProjectRoot() . '/src/Jobs/' . $jobClass . '.php';

        if (file_exists($jobClassPath)) {
            throw new \Exception("Job class $jobClass already exists.");
        }

        $namespace = 'App\\Jobs';
        $projectRoot = $this->getProjectRoot();
        $composerJson = $projectRoot . '/composer.json';
        if (file_exists($composerJson)) {
            $composerConfig = json_decode(file_get_contents($composerJson), true);
            if (isset($composerConfig['name']) && $composerConfig['name'] === 'mita/uranus-http-server') {
                $namespace = 'Mita\\UranusHttpServer\\Jobs';
            } elseif (isset($composerConfig['autoload']['psr-4'])) {
                $psr4 = array_keys($composerConfig['autoload']['psr-4'])[0];
                $namespace = rtrim($psr4, '\\') . '\\Jobs';
            }
        }

        $queuePrefix = $this->queue->getQueuePrefix();
        $queueName = $queuePrefix . $jobName;

        $jobContent = <<<EOT
<?php
namespace $namespace;

use Mita\UranusHttpServer\Jobs\AbstractJob;
use Mita\UranusHttpServer\Jobs\JobInterface;

class $jobClass extends AbstractJob implements JobInterface
{
    public function __construct(string \$queue = '$queueName')
    {
        parent::__construct(\$queue);
    }

    public function execute(): void
    {
        echo "Running $jobClass with queue $queueName\\n";
        sleep(5);
    }

    public function serialize(): string
    {
        return serialize([
            'class' => self::class,
            'id'    => \$this->id,
            'queue' => \$this->queue
        ]);
    }

    public static function unserialize(string \$serialized): self
    {
        \$data = unserialize(\$serialized);
        \$job = new self(\$data['queue']);
        \$job->id = \$data['id'];
        return \$job;
    }
}
EOT;

        file_put_contents($jobClassPath, $jobContent);
        
        return $jobClass;
    }

    private function getProjectRoot(): string
    {
        $dir = getcwd();
        while (!file_exists("$dir/vendor")) {
            $dir = dirname($dir);
            if ($dir === '/') {
                throw new \Exception('Project root not found');
            }
        }
        return $dir;
    }
}
