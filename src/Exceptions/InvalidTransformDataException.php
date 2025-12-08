<?php 

namespace Mita\UranusHttpServer\Exceptions;

class InvalidTransformDataException extends \InvalidArgumentException
{
    public function __construct(
        string $message = 'Invalid data for transformation',
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
