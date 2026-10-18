<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\ResetPasswordVi;
use App\Notifications\VerifyEmailVi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private function googleUser(string $email, bool $verified = true): SocialiteUser
    {
        $user = (new SocialiteUser)->map(['id' => 'g-123', 'name' => 'Gia Nguyen', 'email' => $email, 'avatar' => 'https://x.test/a.png']);
        $user->user = ['email_verified' => $verified];

        return $user;
    }

    private function mockGoogle(SocialiteUser $user): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_registration_sends_a_verification_email_and_starts_unverified(): void
    {
        Notification::fake();

        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/'])->postJson('/api/v1/auth/register', [
            'name' => 'Khách Mới', 'email' => 'new@example.test', 'phone' => '0987654321',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
            'terms_accepted' => '1',
        ])->assertCreated()->assertJsonPath('data.email_verified', false);

        Notification::assertSentTo(User::where('email', 'new@example.test')->first(), VerifyEmailVi::class);
    }

    public function test_unverified_user_cannot_book_or_apply_as_owner_but_can_browse(): void
    {
        Sanctum::actingAs(User::factory()->unverified()->create(['is_active' => true, 'role' => 'customer']));

        $this->postJson('/api/v1/customer/bookings', [])->assertForbidden()->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
        $this->postJson('/api/v1/customer/owner-application', [])->assertForbidden()->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
        $this->getJson('/api/v1/customer/bookings')->assertOk();
    }

    public function test_signed_link_verifies_email_and_tampered_links_are_rejected(): void
    {
        $user = User::factory()->unverified()->create(['is_active' => true]);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->get($url.'x')->assertRedirectContains('status=invalid'); // chữ ký sai/hết hạn
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@x.test')]);
        $this->get($wrongHash)->assertRedirectContains('status=invalid');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        $this->get($url)->assertRedirectContains('status=success');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_verification_email_is_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/email/resend')->assertOk();
        $this->postJson('/api/v1/auth/email/resend')->assertOk();
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmailVi::class, 2);
    }

    public function test_forgot_password_does_not_reveal_whether_the_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $a = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json('message');
        $b = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'])->assertOk()->json('message');

        $this->assertSame($a, $b);
        Notification::assertSentTo($user, ResetPasswordVi::class);
        Notification::assertCount(1);
    }

    public function test_password_can_be_reset_with_a_valid_token_only_once(): void
    {
        $user = User::factory()->unverified()->create(['is_active' => true]);
        $token = Password::createToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];

        $this->postJson('/api/v1/auth/reset-password', ['token' => 'bad'] + $payload)->assertStatus(422);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(password_verify('NewPassword123!', $user->fresh()->password));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(422); // token đã dùng
    }

    public function test_google_callback_creates_a_verified_customer_without_password(): void
    {
        $this->mockGoogle($this->googleUser('gia@example.test'));

        $this->get('/auth/google/callback')->assertRedirectContains(config('frontend.url'));

        $user = User::where('email', 'gia@example.test')->first();
        $this->assertSame('customer', $user->role);
        $this->assertSame('g-123', $user->google_id);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_callback_links_an_existing_account_by_verified_email(): void
    {
        $existing = User::factory()->unverified()->create(['email' => 'gia@example.test']);
        $this->mockGoogle($this->googleUser('gia@example.test'));

        $this->get('/auth/google/callback');

        $this->assertSame('g-123', $existing->fresh()->google_id);
        $this->assertTrue($existing->fresh()->hasVerifiedEmail());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_callback_refuses_unverified_google_email_and_disabled_accounts(): void
    {
        $this->mockGoogle($this->googleUser('fake@example.test', verified: false));
        $this->get('/auth/google/callback')->assertRedirectContains('error=google');
        $this->assertDatabaseMissing('users', ['email' => 'fake@example.test']);

    }

    public function test_google_callback_refuses_disabled_accounts(): void
    {
        User::factory()->create(['email' => 'locked@example.test', 'is_active' => false]);
        $this->mockGoogle($this->googleUser('locked@example.test'));

        $this->get('/auth/google/callback')->assertRedirectContains('error=disabled');
        $this->assertGuest();
    }

    public function test_google_cannot_link_an_existing_account_when_google_email_is_unverified(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.test', 'is_active' => true]);
        $this->mockGoogle($this->googleUser('victim@example.test', verified: false));

        $this->get('/auth/google/callback')->assertRedirectContains('error=google');

        $this->assertNull($victim->fresh()->google_id);
        $this->assertGuest();
    }

    public function test_google_admin_is_redirected_to_the_admin_area(): void
    {
        User::factory()->create(['email' => 'admin@example.test', 'role' => 'admin', 'is_active' => true]);
        $this->mockGoogle($this->googleUser('admin@example.test'));

        $this->get('/auth/google/callback')->assertRedirectContains('/admin');
    }

    public function test_changing_email_resets_verification_and_sends_a_new_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true, 'phone' => '0987654321']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/customer/profile', ['name' => $user->name, 'email' => 'moi@example.test', 'phone' => '0987654321'])->assertOk();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user->fresh(), VerifyEmailVi::class);

        // Giữ nguyên email thì vẫn đã xác thực.
        $user->markEmailAsVerified();
        $this->putJson('/api/v1/customer/profile', ['name' => 'Tên mới', 'email' => 'moi@example.test', 'phone' => '0987654321'])->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_google_only_account_can_set_a_first_password_without_current_one(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $user->forceFill(['password' => null])->save();
        Sanctum::actingAs($user->fresh());

        $this->putJson('/api/v1/customer/profile/password', ['password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertOk();
        $this->assertNotNull($user->fresh()->password);

        // Đã có mật khẩu thì bắt buộc nhập mật khẩu hiện tại.
        $this->putJson('/api/v1/customer/profile/password', ['password' => 'Another123!', 'password_confirmation' => 'Another123!'])->assertStatus(422);
    }
}
