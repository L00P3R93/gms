<?php

namespace App\Exceptions;

use RuntimeException;

class GameApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly string $apiMessage = '',
    ) {
        parent::__construct($message);
    }
}
