<?php
namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Contracts\WorkableRegistry;
use Mita\UranusHttpServer\Queue\WorkerManager;
use Mita\UranusHttpServer\Queue\AsyncJobManager;
use Mita\UranusHttpServer\Jobs\AbstractAsyncJob;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

class StartJobCommand extends Command
{
    protected static $defaultName = 'start:job';

    private WorkableRegistry $registry;
    private ContainerInterface $container;
    private WorkerManager $workerManager;
    private AsyncJobManager $asyncJobManager;
    private LoggerInterface $logger;
    private LoopInterface $loop;

    public function __construct(
        WorkerManager $workerManager, 
        AsyncJobManager $asyncJobManager,
        WorkableRegistry $registry, 
        LoggerInterface $logger,
        ContainerInterface $container,
        LoopInterface $loop)
    {
        $this->registry = $registry;
        $this->workerManager = $workerManager;
        $this->asyncJobManager = $asyncJobManager;
        $this->container = $container;
        $this->logger = $logger;
        $this->loop = $loop;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('start:job')
            ->setDescription('Bắt đầu queue worker cho một job cụ thể')
            ->setHelp('Lệnh này bắt đầu queue worker cho một job cụ thể')
            ->addArgument('job', InputArgument::REQUIRED, 'Tên của job cần xử lý');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobName = $input->getArgument('job');

        $jobClass = $this->registry->get($jobName);
        if (!$jobClass) {
            $output->writeln("<error>Không tìm thấy job: $jobName</error>");
            return Command::FAILURE;
        }

        // Create job instance to check if it's async
        $jobInstance = $this->container->get($jobClass);
        
        if ($jobInstance instanceof AbstractAsyncJob && $jobInstance->isAsync()) {
            $output->writeln("Job $jobName tự config ASYNC mode:");
            $output->writeln("- Max Concurrency: " . $jobInstance->getMaxConcurrency());
            $output->writeln("- Timeout: " . $jobInstance->getTimeout() . "s");
            $output->writeln("- Retry: " . ($jobInstance->shouldRetry() ? 'Yes' : 'No'));
            
            $this->startAsyncProcessing($jobName, $jobInstance);
            
            // Start event loop for async processing
            $this->loop->run();
        } else {
            $output->writeln("Job $jobName chạy SYNC mode (mặc định)");
            $this->startSyncProcessing($jobName, $jobInstance);
        }

        return Command::SUCCESS;
    }
    
    protected function startAsyncProcessing(string $jobName, AbstractAsyncJob $jobInstance): void
    {
        $this->asyncJobManager->startAsyncProcessing($jobName, $jobInstance);
    }
    
    protected function startSyncProcessing(string $jobName, $jobInstance): void
    {
        // Existing sync processing logic
        while (true) {
            $data = $this->workerManager->getNextJob($jobName);
            if ($data) {
                try {
                    $jobInstance->execute($data);
                    $this->logger->info("Đã xử lý một job $jobName");
                } catch (\Exception $e) {
                    $this->logger->error("Lỗi khi xử lý job $jobName: " . $e->getMessage());
                }
            } else {
                sleep(1);
            }
        }
    }
}