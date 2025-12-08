<?php

namespace Mita\UranusHttpServer\Console\Commands;

use OpenApi\Generator;
use Mita\UranusHttpServer\Configs\Config;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class DocsGenerateCommand extends Command
{
    protected static $defaultName = 'docs:generate';
    protected $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate API documentation using swagger-php')
            ->setHelp('This command allows you to generate API documentation based on annotations in your code')
            ->addArgument('output', InputArgument::OPTIONAL, 'The output file for the generated documentation')
            ->addOption('dir', 'd', InputOption::VALUE_OPTIONAL, 'The directory to scan for annotations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $publicPath = __DIR__ . '/../../..' . $this->config->get('public_path', null);
        $swaggerHtmlPath = $publicPath . '/swagger.html';

        if (!$publicPath) {
            throw new RuntimeException("PUBLIC_PATH is not set. Please check your configuration.");
        }

        if (!is_dir($publicPath)) {
            throw new RuntimeException("PUBLIC_PATH does not exist. Please check your configuration.");
        }

        if (!file_exists($swaggerHtmlPath)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('swagger.html does not exist. Would you like to create it now? (y/n) ', false, '/^y$/i');

            if ($helper->ask($input, $output, $question)) {
                $this->getApplication()->find('make:docs')->run($input, $output);
            } else {
                $output->writeln("<error>swagger.html is required to generate documentation. Please create it and try again.</error>");
                return Command::FAILURE;
            }
        }

        // Lấy thông tin từ composer.json
        $composerPath = __DIR__ . '/../../../composer.json';
        if (!file_exists($composerPath)) {
            throw new RuntimeException("composer.json file not found.");
        }

        $composerData = json_decode(file_get_contents($composerPath), true);
        $title = $composerData['name'] ?? 'API Documentation';
        $version = $composerData['version'] ?? '1.0.0';

        $scanDir = $input->getOption('dir') ?: $this->config->get('swagger.scan_dir', __DIR__ . '/../../src/Actions');
        $outputFile = $input->getArgument('output') ?: $this->config->get('swagger.output_file', __DIR__ . '/../../public/swagger.json');

        $composerData = json_decode(file_get_contents($composerPath), true);
        $projectName = $composerData['name'] ?? 'API Documentation';
        $version = $composerData['version'] ?? '1.0.0';
        $description = $composerData['description'] ?? 'API documentation generated automatically.';

        $relativePath = str_replace([realpath(__DIR__ . '/../../../'), '/'], ['', '\\'], realpath($scanDir));
        $namespace = trim($relativePath, '\\');

        $templatePath = __DIR__ . '/../../Swagger/ApiInfo.php.dist';
        if (!file_exists($templatePath)) {
            throw new RuntimeException("ApiInfo.php.dist template file not found.");
        }

        $templateContent = file_get_contents($templatePath);
        $templateContent = str_replace(
            ['$NAMESPACE', '$PROJECT_NAME', '$VERSION', '$DESCRIPTION'],
            [$namespace, $projectName, $version, $description],
            $templateContent
        );

        // Kiểm tra và tạo file ApiInfo.php nếu chưa tồn tại
        $infoFilePath = $scanDir . '/ApiInfo.php';
        if (!file_exists($infoFilePath)) {
            $output->writeln("Creating ApiInfo.php with @OA\Info annotation...");
            file_put_contents($infoFilePath, $templateContent);
        }

        $output->writeln("Generating API documentation...");
        $output->writeln("Scanning directory: $scanDir");
        $output->writeln("Output file: $outputFile");

        // Quét thư mục và sinh tài liệu
        $openapi = Generator::scan([$scanDir]);
        file_put_contents($outputFile, $openapi->toJson());

        $output->writeln("API documentation generated successfully!");

        return Command::SUCCESS;
    }
}
