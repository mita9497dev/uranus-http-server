<?php 
namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Contracts\WorkableRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Str;
use Psr\Container\ContainerInterface;
use Mita\UranusHttpServer\Contracts\AbstractWorkable;

class StartTaskCommand extends Command
{
    protected static $defaultName = 'start:task';

    private WorkableRegistry $registry;
    private ContainerInterface $container;

    public function __construct(WorkableRegistry $registry, ContainerInterface $container)
    {
        $this->registry = $registry;
        $this->container = $container;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Chạy một task cụ thể')
            ->setHelp('Lệnh này cho phép bạn chạy một task cụ thể')
            ->addArgument('task', InputArgument::REQUIRED, 'Tên của task cần chạy');

        $this->addOption(
            'options',
            'o',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
            'Additional options in format: key=value'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $taskName = $input->getArgument('task');
        $taskName = Str::studly($taskName);

        $taskClass = $this->registry->get($taskName);
        if (!$taskClass) {
            if (!Str::endsWith($taskName, 'Task')) {
                $taskName .= 'Task';
            }

            $taskClass = $this->registry->get($taskName);
            if (!$taskClass) {
                $output->writeln("<error>Không tìm thấy task: $taskName</error>");
                return Command::FAILURE;
            }
        }

        $options = $this->parseOptions($input);

        try {
            /** @var string */
            $taskClass = ltrim($taskClass, '\\');

            /** @var AbstractWorkable */
            $task = $this->container->get($taskClass);
            $task->setOptions($options);
        } catch (\Throwable $e) {
            $output->writeln("<error>Lỗi khi khởi tạo task $taskName: " . $e->getMessage() . "</error>");
            $output->writeln("<error>Lỗi khi khởi tạo task $taskName: " . $e->getTraceAsString() . "</error>");
            return Command::FAILURE;
        }

        try {
            $output->writeln("Đang chạy task: $taskName");
            $task->run();
            $output->writeln("Task $taskName đã chạy thành công");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln("<error>Lỗi khi chạy task $taskName: " . $e->getMessage() . "</error>");
            $task->__destruct();
            return Command::FAILURE;
        }
    }

    private function parseOptions(InputInterface $input): array 
    {
        $options = [];

        // Parse các options bổ sung
        $additionalOptions = $input->getOption('options');
        if (!empty($additionalOptions)) {
            foreach ($additionalOptions as $option) {
                if (strpos($option, '=') !== false) {
                    list($key, $value) = explode('=', $option, 2);
                    $options[$key] = $value;
                }
            }
        }

        // Lọc bỏ các giá trị null
        return array_filter($options, function($value) {
            return $value !== null;
        });
    }
}