<?php

namespace Mita\UranusHttpServer\Console\Commands;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class RulesListCommand extends Command
{
    protected static $defaultName = 'rules:list';

    protected function configure(): void
    {
        $this
            ->setDescription('List available Respect\Validation rules with descriptions')
            ->setHelp('This command allows you to list all available Respect\Validation rules directly from the Respect\Validation library along with their descriptions.')
            ->addArgument('name', InputArgument::OPTIONAL, 'The name of the model class');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        $rulesNamespace = 'Respect\Validation\Rules';
        $rulesDirectory = __DIR__ . '/../../../vendor/respect/validation/library/Rules';

        $finder = new Finder();
        $finder->files()->in($rulesDirectory)->name('*.php');

        $output->writeln("<info>Available Respect\Validation Rules:</info>");

        $rulesFound = false;
        $closestMatches = [];
        $thresholdDistance = 3; // Ngưỡng cho phép để xác định kết quả gần đúng

        foreach ($finder as $file) {
            $className = $rulesNamespace . '\\' . $file->getBasename('.php');
            if (class_exists($className)) {
                $reflectionClass = new ReflectionClass($className);
                $ruleName = $reflectionClass->getShortName();

                // If $name is provided, perform fuzzy search
                if ($name) {
                    $distance = levenshtein(strtolower($name), strtolower($ruleName));

                    if ($distance <= $thresholdDistance) {
                        $closestMatches[] = $reflectionClass;
                    }
                } else {
                    // Get class doc comment
                    $docComment = $reflectionClass->getDocComment();
                    $description = $this->extractDescriptionFromDocComment($docComment);

                    // Get constructor parameters if available
                    $constructor = $reflectionClass->getConstructor();
                    if ($constructor) {
                        $paramsDescription = $this->getConstructorParamsDescription($constructor);
                        $description .= "\nParameters: $paramsDescription";
                    }

                    $output->writeln("<comment>{$ruleName}</comment>: $description");
                    $rulesFound = true;
                }
            }
        }

        if ($name && !empty($closestMatches)) {
            foreach ($closestMatches as $match) {
                // Get class doc comment
                $docComment = $match->getDocComment();
                $description = $this->extractDescriptionFromDocComment($docComment);

                // Get constructor parameters if available
                $constructor = $match->getConstructor();
                if ($constructor) {
                    $paramsDescription = $this->getConstructorParamsDescription($constructor);
                    $description .= "\nParameters: $paramsDescription";
                }

                $output->writeln("<comment>{$match->getShortName()}</comment>: $description\r\n");
            }
            $rulesFound = true;
        }

        if (!$rulesFound) {
            $output->writeln("<comment>No validation rules found.</comment>");
        }

        return Command::SUCCESS;
    }

    private function getConstructorParamsDescription(ReflectionMethod $constructor)
    {
        $params = $constructor->getParameters();
        $paramDescriptions = [];

        foreach ($params as $param) {
            $paramType = $param->getType() ? $param->getType()->getName() : 'mixed';
            $paramDescription = '<fg=green>' . $paramType . '</> <fg=blue>$' . $param->getName() . '</>';

            if ($param->isDefaultValueAvailable()) {
                $paramDescription .= ' = ' . var_export($param->getDefaultValue(), true);
            }

            $paramDescriptions[] = $paramDescription;
        }

        return implode(', ', $paramDescriptions);
    }

    private function extractDescriptionFromDocComment($docComment)
    {
        if ($docComment === false) {
            return 'No description available';
        }

        // Normalize line breaks and split the docblock into lines
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $docComment));

        $descriptionLines = [];
        foreach ($lines as $line) {
            // Trim the leading "* " from the docblock line and any leading/trailing whitespace
            $line = trim(preg_replace('/^\s*\*\s?/', '', $line));

            // Skip the lines that don't contain meaningful information
            if (empty($line) || strpos($line, '@') === 0 || $line === '/**' || $line === '*/') {
                continue;
            }

            $descriptionLines[] = $line;
        }

        // Join the description lines into a single string
        return implode(' ', $descriptionLines);
    }
}
