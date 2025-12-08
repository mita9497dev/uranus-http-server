<?php 
namespace Mita\UranusHttpServer\Console\Commands;

use PHPUnit\TextUI\Command as PHPUnitCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class TestRunCommand extends Command
{
    protected static $defaultName = 'test:run';

    protected function configure(): void
    {
        $this->setDescription('Run test cases')
            ->addArgument('test', InputArgument::OPTIONAL, 'The test case class/directory to run')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, 'Filter which tests to run')
            ->addOption('group', 'g', InputOption::VALUE_OPTIONAL, 'Only runs tests from the specified group(s)')
            ->addOption('exclude-group', null, InputOption::VALUE_OPTIONAL, 'Exclude tests from the specified group(s)')
            ->addOption('testsuite', null, InputOption::VALUE_OPTIONAL, 'Only runs the specified test suite')
            ->addOption('coverage-html', null, InputOption::VALUE_OPTIONAL, 'Generate code coverage report in HTML format')
            ->addOption('coverage-clover', null, InputOption::VALUE_OPTIONAL, 'Generate code coverage report in Clover XML format')
            ->addOption('stop-on-failure', 's', InputOption::VALUE_NONE, 'Stop execution upon first error or failure')
            // ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Output more verbose information')
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Display debugging information');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $arguments = [];

        // Add test path if specified
        if ($test = $input->getArgument('test')) {
            $arguments[] = $test;
        }

        // Add filter option
        if ($filter = $input->getOption('filter')) {
            $arguments[] = '--filter';
            $arguments[] = $filter;
        }

        // Add group options
        if ($group = $input->getOption('group')) {
            $arguments[] = '--group';
            $arguments[] = $group;
        }

        if ($excludeGroup = $input->getOption('exclude-group')) {
            $arguments[] = '--exclude-group';
            $arguments[] = $excludeGroup;
        }

        // Add testsuite option
        if ($testsuite = $input->getOption('testsuite')) {
            $arguments[] = '--testsuite';
            $arguments[] = $testsuite;
        }

        // Add coverage options
        if ($coverageHtml = $input->getOption('coverage-html')) {
            $arguments[] = '--coverage-html';
            $arguments[] = $coverageHtml;
        }

        if ($coverageClover = $input->getOption('coverage-clover')) {
            $arguments[] = '--coverage-clover';
            $arguments[] = $coverageClover;
        }

        // Add other flags
        if ($input->getOption('stop-on-failure')) {
            $arguments[] = '--stop-on-failure';
        }

        // if ($input->getOption('verbose')) {
        //     $arguments[] = '--verbose';
        // }

        if ($input->getOption('debug')) {
            $arguments[] = '--debug';
        }

        try {
            $command = new PHPUnitCommand();
            return $command->run($arguments, true);
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
