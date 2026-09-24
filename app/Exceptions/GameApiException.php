<?php

namespace App\Exceptions;

use RuntimeException;

class GameApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>|string>  $errors  Field errors from a 422 response.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly string $apiMessage = '',
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
