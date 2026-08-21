<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FieldType extends Model
{
    use HasFactory;

    protected $fillable = [
        'sport_category_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sportCategory()
    {
        return $this->belongsTo(SportCategory::class);
    }

    public function fields()
    {
        return $this->hasMany(SportsField::class);
    }
}
