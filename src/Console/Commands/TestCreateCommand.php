<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Support\Str;
use Mita\UranusHttpServer\Configs\Config;
use Symfony\Component\Console\Question\ChoiceQuestion;

class TestCreateCommand extends Command
{
    protected static $defaultName = 'test:create';

    protected Config $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new test case class')
            ->setHelp('This command allows you to create a new test case class in the user\'s tests directory.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the test case class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $testCasePath = $input->getArgument('name');
        if (strpos($testCasePath, '/') !== -1) {
            $parts = explode('/', $testCasePath);
        } else if (strpos($testCasePath, "\\") !== -1) {
            $parts = explode("\\", $testCasePath);
        } else {
            $parts = [$testCasePath];
        }

        $parts = array_map('ucfirst', $parts);

        $rawTestCaseName = array_pop($parts);
        $words = preg_split('/(?=[A-Z])/', $rawTestCaseName);
        if (!Str::endsWith($rawTestCaseName, 'TestCase')) {
            $testCaseName = implode('', array_map('ucfirst', array_map('strtolower', $words))) . 'TestCase';
        } else {
            $testCaseName = implode('', array_map('ucfirst', array_map('strtolower', $words)));
        }
        $subNamespace = implode('\\', array_map('ucfirst', $parts));

        $projectRoot = $this->getProjectRoot();
        $testCaseNamespace = 'Tests' . ($subNamespace ? '\\' . $subNamespace : '');
        $testCaseDir = $projectRoot . '/Tests/' . implode('/', $parts);

        if (!is_dir($testCaseDir)) {
            mkdir($testCaseDir, 0755, true);
        }

        $testCaseTemplate = <<<EOT
<?php 
namespace $testCaseNamespace;

use Tests\TestCase;

class $testCaseName extends TestCase
{
    public function test_some_thing()
    {
        // \$this->loginAs('admin_ut');
        // \$this->assertResponseStatus(\$response, 200);
    }
}
EOT;
        
       
        $testCaseFilePath = $testCaseDir . '/' . $testCaseName . '.php';
        if (file_exists($testCaseFilePath)) {
            $output->writeln("<error>Test Case $testCaseName already exists!</error>");
            return Command::FAILURE;
        }

        file_put_contents($testCaseFilePath, $testCaseTemplate);
        $output->writeln("<info>Test Case $testCaseName created successfully in $testCaseFilePath</info>");

        return Command::SUCCESS;
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
