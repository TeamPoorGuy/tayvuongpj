<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OwnerDocument extends Model
{
    public const TYPES = ['id_front', 'id_back', 'business_license', 'land_right'];

    /** Loại bắt buộc khi nộp hồ sơ lần đầu (GPKD tuỳ chọn: hộ cá nhân cho thuê sân thường không có). */
    public const REQUIRED = ['id_front', 'id_back'];

    protected $fillable = ['field_owner_profile_id', 'type', 'path', 'original_name'];

    public function profile()
    {
        return $this->belongsTo(FieldOwnerProfile::class, 'field_owner_profile_id');
    }
}
