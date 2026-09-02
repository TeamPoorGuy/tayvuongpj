<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case FieldOwner = 'field_owner';
    case Customer = 'customer';
}
