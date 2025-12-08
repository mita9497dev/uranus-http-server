<?php

namespace Mita\UranusHttpServer\Repositories;

interface RepositoryCollectionInterface
{
    /**
     * Get list of repositories to register
     * 
     * @return string[]
     */
    public function getRepositories(): array;
}