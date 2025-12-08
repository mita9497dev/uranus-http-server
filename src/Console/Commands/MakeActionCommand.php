<?php

namespace Mita\UranusHttpServer\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\Question;

class MakeActionCommand extends Command
{
    protected static $defaultName = 'make:action';

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new action')
            ->setHelp('This command creates a new action in the src/Actions directory of the project')
            ->addArgument('name', InputArgument::REQUIRED, 'Action name (example: users/AddUser)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actionPath = $input->getArgument('name');
        if (strpos($actionPath, '/') !== -1) {
            $parts = explode('/', $actionPath);
        } else if (strpos($actionPath, "\\") !== -1) {
            $parts = explode("\\", $actionPath);
        } else {
            $parts = [$actionPath];
        }

        $parts = array_map('ucfirst', $parts);

        $rawActionName = array_pop($parts);
        $words = preg_split('/(?=[A-Z])/', $rawActionName);
        $actionName = implode('', array_map('ucfirst', array_map('strtolower', $words))) . 'Action';
        $subNamespace = implode('\\', array_map('ucfirst', $parts));

        $projectRoot = $this->getProjectRoot();
        $composerJson = $projectRoot . '/composer.json';
        $namespace = $this->getNamespace($composerJson);

        $actionNamespace = $namespace . '\\Actions' . ($subNamespace ? '\\' . $subNamespace : '');
        $actionDir = $projectRoot . '/src/Actions/' . implode('/', $parts);
        $actionFile = $actionDir . '/' . $actionName . '.php';

        if (!is_dir($actionDir)) {
            mkdir($actionDir, 0755, true);
        }

        $questionHelper = new QuestionHelper();
        $baseClassQuestion = new ChoiceQuestion(
            'Extend base class:',
            ['AbstractHtmlAction (default)', 'AbstractJsonAction', 'AbstractAction'],
            0
        );
        $baseClassQuestion->setErrorMessage('Option %s is invalid.');
        $baseClass = $questionHelper->ask($input, $output, $baseClassQuestion);

        try {
            $templateConstant = '';
            $htmlFile = '';
            if ($baseClass === 'AbstractHtmlAction') {
                $templateQuestion = new Question('Enter the path for the HTML template (e.g., path/to/folder/htmlname): ');
                $templatePath = $questionHelper->ask($input, $output, $templateQuestion);
                
                $htmlDir = $projectRoot . '/src/Views/' . dirname($templatePath);
                if (!is_dir($htmlDir)) {
                    mkdir($htmlDir, 0755, true);
                }
                $htmlFile = $htmlDir . '/' . basename($templatePath) . '.html';
                $templateConstant = <<<EOT

    public const TEMPLATE = '$templatePath.html';\n
EOT;
            }

            $ownerShipMethodQuestion = new ChoiceQuestion(
                'Extend authorizeOwnerShip method?',
                ['No (default)', 'Yes'],
                0
            );
            $ownerShipMethodQuestion->setErrorMessage('Option %s is invalid.');
            $ownerShipMethod = $questionHelper->ask($input, $output, $ownerShipMethodQuestion) === 'Yes';

            $transformerQuestion = new ChoiceQuestion(
                'Add transformer?',
                ['No (default)', 'Yes'],
                0
            );
            $transformer = $questionHelper->ask($input, $output, $transformerQuestion) === 'Yes';

            $validatorQuestion = new ChoiceQuestion(
                'Add validator?',
                ['No (default)', 'Yes'],
                0
            );
            $validator = $questionHelper->ask($input, $output, $validatorQuestion) === 'Yes';

            $actionContent = $this->generateActionContent($actionNamespace, $actionName, $baseClass, $ownerShipMethod, $transformer, $validator, $templateConstant);

            file_put_contents($actionFile, $actionContent);
            $output->writeln("<info>Action $actionName has been created successfully at $actionFile</info>");

            if (isset($htmlDir) && isset($htmlFile) && !file_exists($htmlFile)) {
                if (!is_dir($htmlDir)) {
                    $parts = explode('/', $htmlDir);
                    $parts = array_map('ucfirst', $parts);
                    $htmlDir = $projectRoot . '/src/Views/' . implode('/', $parts);
                    if (!is_dir($htmlDir)) {
                        mkdir($htmlDir, 0755, true);
                    }
                }
                
                file_put_contents($htmlFile, '<!-- Add your HTML content here -->');
                $output->writeln("<info>Created HTML template: $htmlFile</info>");
            }
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
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

    private function getNamespace(string $composerJsonPath): string
    {
        if (!file_exists($composerJsonPath)) {
            throw new \RuntimeException('composer.json file not found');
        }

        $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
        if (isset($composerConfig['autoload']['psr-4'])) {
            return rtrim(array_keys($composerConfig['autoload']['psr-4'])[0], '\\');
        }

        throw new \RuntimeException('Namespace not found in composer.json');
    }

    private function generateActionContent(
        string $namespace, 
        string $className, 
        string $baseClass, 
        bool $ownerShipMethod, 
        bool $transformer,
        bool $validator,
        string $templateConstant = ''
    ): string {
        $use = "use Mita\UranusHttpServer\Actions\\$baseClass;\n";
        $use .= "use Mita\UranusHttpServer\Actions\AuthorizableInterface;\n";
        $use .= "use Psr\Http\Message\ServerRequestInterface;\n";
        $use .= "use Psr\Http\Message\ResponseInterface;\n";

        $properties = '';

        if ($validator) {
            $use .= "use Mita\UranusHttpServer\Services\ValidatorService;\n";
            $use .= "use Respect\Validation\Validator as v;\n";
            $properties .= <<<EOT

    private ValidatorService \$validatorService;\n
EOT;
        }
        
        if ($transformer) {
            $use .= "use Mita\UranusHttpServer\Services\TransformerService;\n";
            $properties .= <<<EOT

    private TransformerService \$transformerService;\n
EOT;
        }

        $constructor = '';
        if ($validator && $transformer) {
            $constructor = <<<EOT
    public function __construct(
        ValidatorService \$validatorService, 
        TransformerService \$transformerService
    )
    {
        \$this->validatorService = \$validatorService;
        \$this->transformerService = \$transformerService;
    }
EOT;
        } elseif ($validator) {
            $constructor = <<<EOT
    public function __construct(ValidatorService \$validatorService)
    {
        \$this->validatorService = \$validatorService;
    }
EOT;
        } elseif ($transformer) {
            $constructor = <<<EOT
    public function __construct(TransformerService \$transformerService)
    {
        \$this->transformerService = \$transformerService;
    }
EOT;
        }

        $implements = 'implements AuthorizableInterface';
        $authorizableMethods = '';

        if ($ownerShipMethod) {
            $use .= "use Mita\UranusHttpServer\Models\HasUserAccessScopeInterface;\n";
            $authorizableMethods = $this->getAuthorizableMethods();
        }

        return <<<EOT
<?php
namespace $namespace;

$use

class $className extends $baseClass $implements
{
    public const POLICY_NAME = null;

    public const ACCEPT_ROLES = null;
    
    // TODO: Change this to the actual auth payload class
    public const AUTH_PAYLOAD_CLASS = null;
$templateConstant
$properties
$constructor

    public function validate(ServerRequestInterface \$request, array \$args = []): bool
    {
        return true;
    }

    public function __invoke(ServerRequestInterface \$request, ResponseInterface \$response, array \$args): ResponseInterface
    {
        // TODO: Implement your action logic $className
        return \$this->json(\$response, [
            'message' => 'Hello from $className'
        ]);
    }
$authorizableMethods
}
EOT;
    }

    private function getAuthorizableMethods(): string
    {
        return <<<EOT

    public function authorizeOwnerShip(\$ownerId, HasUserAccessScopeInterface \$resource): bool
    {
        return true;
    }
EOT;
    }
}
