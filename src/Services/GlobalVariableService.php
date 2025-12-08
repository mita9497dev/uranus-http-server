<?php

namespace Mita\UranusHttpServer\Services;

use Mita\UranusHttpServer\Actions\GlobalVariableProviderInterface;
use Slim\Views\Twig;

class GlobalVariableService
{
    private $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    public function addGlobal(string $key, $value): void
    {
        $this->twig->getEnvironment()->addGlobal($key, $value);
    }

    public function addGlobals(array $variables): void
    {
        foreach ($variables as $key => $value) {
            $this->addGlobal($key, $value);
        }
    }

    public function loadForAction($action)
    {
        if (is_object($action) && $action instanceof GlobalVariableProviderInterface) {
            $this->addGlobals($action->getGlobalVariables());
        }
    }
}
