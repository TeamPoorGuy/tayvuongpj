<?php

namespace App\Exceptions;

class ConflictException extends DomainException
{
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message, 409, $errors);
    }
}
