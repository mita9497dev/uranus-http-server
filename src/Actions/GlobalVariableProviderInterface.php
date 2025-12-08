<?php

namespace Mita\UranusHttpServer\Actions;

interface GlobalVariableProviderInterface
{
    public function getGlobalVariables(): array;
}
