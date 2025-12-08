<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Mita\UranusHttpServer\Configs\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Exception\RuntimeException;

class MakeDocsCommand extends Command
{
    protected static $defaultName = 'make:docs';
    protected $config;

    public function __construct(Config $config)
    {
        parent::__construct();
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a swagger.html file from the template')
            ->setHelp('This command copies swagger_template.html to the public path and renames it to swagger.html');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basedir = getcwd();
        $publicPath = $this->config->get('public_path', null);
        
        if (!$publicPath) {
            $output->writeln("<error>PUBLIC_PATH is not set. Please check your configuration.</error>");
            return Command::FAILURE;
        }
        if ($publicPath[0] !== '/') {
            $publicPath = $basedir . '/' . $publicPath;
            
        } else {
            $publicPath = $basedir . $publicPath;
        }

        if (!$publicPath) {
            throw new RuntimeException("PUBLIC_PATH is not set. Please check your configuration.");
        }

        if (!is_dir($publicPath)) {
            throw new RuntimeException("PUBLIC_PATH does not exist. Please check your configuration.");
        }

        $templatePath = __DIR__ . '/../../Swagger/swagger_template.html';

        $destinationPath = $publicPath . '/swagger.html';

        if (!file_exists($templatePath)) {
            throw new RuntimeException("Swagger template file does not exist.");
        }

        if (copy($templatePath, $destinationPath)) {
            $output->writeln("Swagger template successfully copied to: $destinationPath");
            return Command::SUCCESS;
        }

        $output->writeln("Failed to copy swagger template.");
        return Command::FAILURE;
    }
}
