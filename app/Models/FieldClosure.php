<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'sports_field_id',
        'start_date',
        'end_date',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function sportsField()
    {
        return $this->belongsTo(SportsField::class);
    }
}
