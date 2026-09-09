<?php

namespace App\Exceptions;

use RuntimeException;

class MpesaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly string $errorCode = '',
        public readonly ?array $responseBody = null,
    ) {
        parent::__construct($message);
    }
}
