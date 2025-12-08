<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeUranusCommand extends Command
{
    protected static $defaultName = 'make:uranus';

    protected function configure(): void
    {
        $this
            ->setDescription('Create Uranus CLI file')
            ->setHelp('This command allows you to create Uranus CLI file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = getcwd() . '/uranus';

        if (!file_exists($filePath)) {
            $content = <<<EOD
#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

\$command = implode(' ', array_slice(\$argv, 1));
passthru(__DIR__ . '/vendor/bin/uranus ' . \$command);
EOD;
            file_put_contents($filePath, $content);
            chmod($filePath, 0755);
            $output->writeln("Uranus CLI file created successfully.\n");
        } else {
            $output->writeln("Uranus CLI file already exists.\n");
        }

        return Command::SUCCESS;
    }
}
