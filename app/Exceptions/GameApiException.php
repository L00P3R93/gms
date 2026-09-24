<?php

namespace App\Exceptions;

use RuntimeException;

class GameApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>|string>  $errors  Field errors from a 422 response.
     * @param  string|null  $errorCode  KadiApi's machine-readable `code`, when the response has one.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly string $apiMessage = '',
        public readonly array $errors = [],
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
