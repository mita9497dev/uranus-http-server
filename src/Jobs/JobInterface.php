<?php
namespace Mita\UranusHttpServer\Jobs;

use Mita\UranusHttpServer\Contracts\WorkableInterface;

interface JobInterface extends WorkableInterface
{
    public function getId(): string;
    public static function getQueue(): string;
}