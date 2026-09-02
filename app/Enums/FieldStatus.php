<?php

namespace App\Enums;

enum FieldStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Inactive = 'inactive';
}
