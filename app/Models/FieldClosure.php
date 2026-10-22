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
        'start_time',
        'end_time',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    protected static function booted(): void
    {
        // Chuẩn hoá "H:i" → "H:i:s" để so sánh chuỗi giờ giống nhau ở mọi CSDL.
        static::saving(function (self $closure) {
            foreach (['start_time', 'end_time'] as $column) {
                if ($closure->{$column} !== null) {
                    $closure->{$column} = Booking::normalizeTime($closure->{$column});
                }
            }
        });
    }

    /** Đóng cả ngày (không giới hạn khung giờ). */
    public function isWholeDay(): bool
    {
        return $this->start_time === null;
    }

    public function sportsField()
    {
        return $this->belongsTo(SportsField::class);
    }
}
