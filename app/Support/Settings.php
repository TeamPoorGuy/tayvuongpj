<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class Settings
{
    /** @var array<string, mixed>|null */
    protected static ?array $cache = null;

    /**
     * Lấy giá trị cài đặt: ưu tiên từ DB, sau đó fallback sang config('booking.*'), cuối cùng là $default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        static::load();

        if (static::$cache !== null && array_key_exists($key, static::$cache)) {
            $val = static::$cache[$key];

            return $val !== null ? $val : config("booking.{$key}", $default);
        }

        return config("booking.{$key}", $default);
    }

    /**
     * Lấy giá trị cài đặt dưới dạng int.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    /**
     * Lấy giá trị cài đặt dưới dạng float.
     */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        return (float) static::get($key, $default);
    }

    /**
     * Lấy giá trị cài đặt dưới dạng string.
     */
    public static function getString(string $key, string $default = ''): string
    {
        return (string) static::get($key, $default);
    }

    /**
     * Lưu hoặc cập nhật một key cài đặt vào DB.
     */
    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);

        if (static::$cache !== null) {
            static::$cache[$key] = (string) $value;
        }
    }

    /**
     * Lưu nhiều key cùng lúc.
     *
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::set($key, $value);
        }
    }

    /**
     * Xóa cache in-memory.
     */
    public static function clearCache(): void
    {
        static::$cache = null;
    }

    /**
     * Trả về danh sách cài đặt công khai cho khách, trang chính sách và màn đặt sân.
     *
     * @return array<string, mixed>
     */
    public static function public(): array
    {
        return [
            'commission_percent' => static::getInt('commission_percent', 8),
            'statement_due_days' => static::getInt('statement_due_days', 7),
            'deposit_percent' => static::getInt('deposit_percent', 30),
            'payment_window_hours' => static::getInt('payment_window_hours', 2),
            'free_cancel_hours' => static::getInt('free_cancel_hours', static::getInt('late_cancel_hours', 4)),
            'max_pending_bookings' => static::getInt('max_pending_bookings', 3),
            'max_advance_days' => static::getInt('max_advance_days', 30),
            'platform_bank_name' => static::getString('platform_bank_name', 'Ngân hàng TMCP Ngoại thương Việt Nam (Vietcombank)'),
            'platform_bank_account' => static::getString('platform_bank_account', '1234567890'),
            'platform_bank_holder' => static::getString('platform_bank_holder', 'CONG TY SPORTHUB VIET NAM'),
            'support_email' => static::getString('support_email', 'hotro@sporthub.vn'),
            'support_hotline' => static::getString('support_hotline', '1900 6868'),
            'owner_terms_version' => static::getString('owner_terms_version', '2026-10'),
        ];
    }

    /**
     * Tải toàn bộ settings từ DB vào static cache.
     */
    protected static function load(): void
    {
        if (static::$cache !== null) {
            return;
        }

        try {
            if (! Schema::hasTable('settings')) {
                static::$cache = [];

                return;
            }

            static::$cache = Setting::pluck('value', 'key')->all();
        } catch (\Throwable) {
            static::$cache = [];
        }
    }
}
