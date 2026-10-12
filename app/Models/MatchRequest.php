<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchRequest extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_FULL = 'full';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id',
        'sport_category_id',
        'sports_field_id',
        'title',
        'play_date',
        'start_time',
        'end_time',
        'skill_level',
        'players_needed',
        'joined_count',
        'description',
        'contact_phone',
        'status',
    ];

    protected $casts = [
        'play_date' => 'date',
        'players_needed' => 'integer',
        'joined_count' => 'integer',
    ];

    public function host()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sportCategory()
    {
        return $this->belongsTo(SportCategory::class);
    }

    public function sportsField()
    {
        return $this->belongsTo(SportsField::class);
    }

    public function participants()
    {
        return $this->hasMany(MatchParticipant::class);
    }

    public function isFull(): bool
    {
        return $this->joined_count >= ($this->players_needed + 1);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
