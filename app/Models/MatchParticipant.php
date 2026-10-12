<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchParticipant extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'match_request_id',
        'user_id',
        'status',
        'message',
    ];

    public function matchRequest()
    {
        return $this->belongsTo(MatchRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
