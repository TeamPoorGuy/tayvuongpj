<?php

namespace App\Policies;

use App\Models\SportsField;
use App\Models\User;

class SportsFieldPolicy
{
    public function manage(User $user, SportsField $field): bool
    {
        return $user->id === $field->field_owner_id;
    }
}
