<?php

namespace App\Exceptions;

use RuntimeException;

class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
