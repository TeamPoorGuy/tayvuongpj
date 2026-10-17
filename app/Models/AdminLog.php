<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'action',
        'subject_type',
        'subject_id',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Ghi nhận một hành động của admin vào nhật ký.
     */
    public static function record(string $action, mixed $subject = null, array $meta = [], ?User $admin = null): self
    {
        $adminId = $admin?->id ?? auth()->id();

        $subjectType = null;
        $subjectId = null;

        if ($subject instanceof Model) {
            $subjectType = $subject->getMorphClass();
            $subjectId = (string) $subject->getKey();
        } elseif (is_string($subject)) {
            $subjectType = $subject;
        }

        return static::create([
            'admin_id' => $adminId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => empty($meta) ? null : $meta,
            'created_at' => now(),
        ]);
    }
}
