<?php

namespace App\Support;

use Illuminate\Contracts\Validation\Rule;

/** Quy tắc nhập liệu dùng chung, để các FormRequest không chép tay regex. */
class InputRules
{
    /** Số điện thoại VN: bắt đầu bằng 0, 10–11 chữ số. */
    public const PHONE = 'regex:/^0[0-9]{9,10}$/';

    /** Họ tên: chỉ chữ cái (có dấu), khoảng trắng, dấu nháy, chấm, gạch nối. */
    public const PERSON_NAME = "regex:/^[\\pL\\s'.-]+$/u";

    /** Tên chủ tài khoản: in hoa, không dấu (VietQR yêu cầu). */
    public const BANK_HOLDER = 'regex:/^[A-Z ]+$/';

    public const BANK_ACCOUNT = 'regex:/^[0-9]{6,20}$/';

    /** Số giấy phép kinh doanh / mã số thuế. */
    public const LICENSE_NO = 'regex:/^[0-9A-Za-z\/-]{5,20}$/';

    public const MAX_MONEY = 100000000;

    /** @return list<string|Rule> */
    public static function phone(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', self::PHONE];
    }

    public static function personName(): array
    {
        return ['required', 'string', 'max:100', self::PERSON_NAME];
    }

    public static function bankHolder(): array
    {
        return ['required', 'string', 'max:50', self::BANK_HOLDER];
    }

    /** Số tiền VND nguyên, trong giới hạn cột decimal(12,2). */
    public static function money(int $min = 1000): array
    {
        return ['required', 'integer', 'min:'.$min, 'max:'.self::MAX_MONEY];
    }
}
