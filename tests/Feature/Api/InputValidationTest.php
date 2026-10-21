<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Ràng buộc nhập liệu: số chỉ nhận chữ số, tên không chứa số, cài đặt không được để trống. */
class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $override = []): array
    {
        return array_merge([
            'name' => 'Nguyễn Văn A', 'email' => 'a@example.com', 'phone' => '0901234567',
            'password' => 'matkhau123', 'password_confirmation' => 'matkhau123', 'terms_accepted' => true,
        ], $override);
    }

    public static function badRegisterInputs(): array
    {
        return [
            'sđt có chữ' => [['phone' => '09012abc67'], 'phone'],
            'sđt không bắt đầu bằng 0' => [['phone' => '1901234567'], 'phone'],
            'sđt quá ngắn' => [['phone' => '090123'], 'phone'],
            'tên có số' => [['name' => 'Nguyen 123'], 'name'],
            'mật khẩu toàn chữ' => [['password' => 'matkhauchu', 'password_confirmation' => 'matkhauchu'], 'password'],
            'mật khẩu quá dài' => [['password' => str_repeat('a1', 40), 'password_confirmation' => str_repeat('a1', 40)], 'password'],
        ];
    }

    #[DataProvider('badRegisterInputs')]
    public function test_register_rejects_bad_input(array $override, string $key): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload($override))
            ->assertStatus(422)->assertJsonValidationErrors($key);
    }

    public function test_register_errors_are_specific_and_keyed_by_field(): void
    {
        User::factory()->create(['email' => 'trung@example.com']);

        $this->postJson('/api/v1/auth/register', $this->registerPayload([
            'name' => 'Nguyen 123', 'email' => 'trung@example.com', 'password_confirmation' => 'khac12345',
        ]))->assertStatus(422)->assertJsonPath('errors.name.0', 'Họ tên chỉ gồm chữ cái, không chứa chữ số hoặc ký tự đặc biệt.')
            ->assertJsonPath('errors.email.0', 'Email này đã được đăng ký. Hãy đăng nhập hoặc dùng email khác.')
            ->assertJsonPath('errors.password.0', 'Mật khẩu xác nhận không khớp.');
    }

    public static function badSettings(): array
    {
        return [
            'cọc bỏ trống' => [['deposit_percent' => ''], 'deposit_percent'],
            'hoa hồng > 100' => [['commission_percent' => 101], 'commission_percent'],
            'số tài khoản có chữ' => [['platform_bank_account' => '12ab3456'], 'platform_bank_account'],
            'chủ tài khoản có dấu' => [['platform_bank_holder' => 'Công ty'], 'platform_bank_holder'],
            'hotline có chữ' => [['support_hotline' => '1900abcd'], 'support_hotline'],
        ];
    }

    #[DataProvider('badSettings')]
    public function test_admin_settings_reject_bad_input(array $payload, string $key): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]));

        $this->putJson('/api/v1/admin/settings', $payload)->assertStatus(422)->assertJsonValidationErrors($key);
    }

    public function test_statement_period_must_be_a_real_month(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]));

        $this->postJson('/api/v1/admin/statements/generate', ['period' => '2026-13'])
            ->assertStatus(422)->assertJsonValidationErrors('period');
    }
}
