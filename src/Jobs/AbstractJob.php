<?php
namespace Mita\UranusHttpServer\Jobs;

use Mita\UranusHttpServer\Contracts\AbstractWorkable;

abstract class AbstractJob extends AbstractWorkable implements JobInterface
{
    protected string $id;

    public function __construct()
    {
        $this->id = uniqid('job_', true);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public static function getQueue(): string
    {
        return static::class . '_queue';
    }

    abstract public function execute(array $data = []): void;
}