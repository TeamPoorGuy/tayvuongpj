<?php

namespace App\Exceptions;

class ForbiddenException extends DomainException
{
    public function __construct(string $message = 'Bạn không có quyền thực hiện thao tác này.')
    {
        parent::__construct($message, 403);
    }
}
