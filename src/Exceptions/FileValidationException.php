<?php

namespace Mita\UranusHttpServer\Exceptions;

class FileValidationException extends \InvalidArgumentException
{
    private array $errors;

    public function __construct(array $errors, int $code = 422)
    {
        $this->errors = $errors;
        parent::__construct(implode(PHP_EOL, $errors), $code);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}