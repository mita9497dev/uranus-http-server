<?php

namespace Mita\UranusHttpServer\Testing;

trait DatabaseAuthenticationTrait
{
    protected function setUpDatabase(): void
    {
        $this->runTestSeeder();
    }

    abstract protected function runTestSeeder(): void;
}