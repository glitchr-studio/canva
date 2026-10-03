<?php

namespace Canva\Exception;

/** The API answered with an error: its HTTP status, and its own code when it gave one. */
class ApiException extends CanvaException
{
    public function __construct(string $message, public readonly int $status, public readonly ?string $apiCode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }
}
