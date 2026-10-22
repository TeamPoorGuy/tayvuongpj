<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintAttachment extends Model
{
    protected $fillable = ['complaint_id', 'user_id', 'path', 'original_name'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
