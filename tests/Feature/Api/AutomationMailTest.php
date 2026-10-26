<?php

namespace Tests\Feature\Api;

use App\Mail\ComplaintFiled;
use App\Mail\ComplaintHandled;
use App\Mail\Notice;
use App\Mail\StatementReminder;
use App\Mail\WalletCredited;
use App\Mail\WithdrawalProcessed;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\OwnerStatement;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Thông báo tự động: huỷ đơn, hết hạn giữ chỗ, duyệt sân, mời đánh giá và nhắc trước giờ chơi. */
class AutomationMailTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    private SportsField $field;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => '012345678901', 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id, 'field_type_id' => $type->id, 'name' => 'Sân A', 'slug' => 'san-a',
            'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
    }

    private function confirmed(Carbon $start, array $attributes = []): Booking
    {
        $payment = Payment::create(['user_id' => $this->customer->id, 'code' => 'SH'.strtoupper(substr(md5((string) microtime(true)), 0, 6)), 'option' => 'deposit', 'total' => 100000, 'amount' => 30000, 'status' => 'paid', 'due_at' => now(), 'paid_at' => now()]);

        return Booking::create($attributes + [
            'user_id' => $this->customer->id, 'sports_field_id' => $this->field->id, 'payment_id' => $payment->id,
            'booking_date' => $start->toDateString(), 'start_time' => $start->format('H:i'), 'end_time' => $start->copy()->addHour()->format('H:i'),
            'total_price' => 100000, 'status' => 'confirmed', 'payment_status' => 'paid', 'payment_option' => 'deposit', 'payment_amount' => 30000,
            'check_in_code' => (string) random_int(100000, 999999),
        ]);
    }

    public function test_customer_cancelling_notifies_the_owner_and_confirms_the_refund_to_the_customer(): void
    {
        $booking = $this->confirmed(now()->addDays(3)->setTime(18, 0));
        Sanctum::actingAs($this->customer);

        $this->patchJson("/api/v1/customer/bookings/{$booking->id}/cancel", ['cancel_reason' => 'Bận việc'])->assertOk();

        Mail::assertQueued(Notice::class, fn ($m) => $m->hasTo($this->owner->email) && str_contains($m->heading, 'Khách đã huỷ'));
        Mail::assertQueued(Notice::class, fn ($m) => $m->hasTo($this->customer->email) && str_contains($m->lines[1], '30.000đ vào ví'));
        $this->assertEquals(30000, $this->customer->fresh()->wallet_balance);
    }

    public function test_an_expired_hold_tells_the_customer(): void
    {
        $booking = $this->confirmed(now()->addDays(3)->setTime(18, 0), ['status' => 'pending', 'payment_status' => 'awaiting']);
        Payment::whereKey($booking->payment_id)->update(['status' => 'awaiting', 'paid_at' => null, 'due_at' => now()->subMinute()]);

        $this->artisan('bookings:sync')->assertSuccessful();

        Mail::assertQueued(Notice::class, fn ($m) => $m->hasTo($this->customer->email) && $m->heading === 'Giữ chỗ đã hết hạn');
    }

    public function test_admin_review_of_a_field_tells_the_owner(): void
    {
        $this->field->update(['status' => 'pending']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        $this->patchJson("/api/v1/admin/fields/{$this->field->id}/verify", ['status' => 'rejected', 'rejection_reason' => 'Thiếu ảnh sân.'])->assertOk();

        Mail::assertQueued(Notice::class, fn ($m) => $m->hasTo($this->owner->email) && str_contains($m->heading, 'không được duyệt') && str_contains($m->lines[0], 'Thiếu ảnh sân.'));
    }

    public function test_finished_checked_in_booking_invites_a_review_but_a_no_show_does_not(): void
    {
        $past = now()->subHours(5);
        $this->confirmed($past, ['checked_in_at' => $past]);
        $this->confirmed($past->copy()->subDay(), []); // không check-in → vắng mặt

        $this->artisan('bookings:sync')->assertSuccessful();

        Mail::assertQueued(Notice::class, 1);
        Mail::assertQueued(Notice::class, fn ($m) => $m->heading === 'Bạn thấy trận đấu hôm nay thế nào?');
    }

    public function test_reminder_goes_out_once_two_hours_before_with_the_check_in_code(): void
    {
        $soon = $this->confirmed(now()->addMinutes(90));
        $this->confirmed(now()->addHours(2)->addMinutes(40)); // còn xa: chưa nhắc

        $this->artisan('bookings:remind')->assertSuccessful();
        $this->artisan('bookings:remind')->assertSuccessful();

        Mail::assertQueued(Notice::class, 1);
        Mail::assertQueued(Notice::class, fn ($m) => str_contains($m->lines[1], $soon->check_in_code));
        $this->assertNotNull($soon->fresh()->reminded_at);
    }

    public function test_all_automation_mail_templates_render_successfully(): void
    {
        $noticeHtml = (new Notice('Tiêu đề test', ['Dòng 1', 'Dòng 2'], 'Xem ngay', '/customer/wallet'))->render();
        $this->assertStringContainsString('Tiêu đề test', $noticeHtml);
        $this->assertStringContainsString('Dòng 1', $noticeHtml);

        $walletHtml = (new WalletCredited($this->customer, 50000, 'Hoàn tiền huỷ sân'))->render();
        $this->assertStringContainsString('50.000', $walletHtml);
        $this->assertStringContainsString('Hoàn tiền huỷ sân', $walletHtml);

        $withdrawal = Withdrawal::create([
            'user_id' => $this->customer->id,
            'amount' => 100000,
            'bank_bin' => '970422',
            'bank_name' => 'MB Bank',
            'account_number' => '0123456789',
            'account_holder' => 'NGUYEN VAN A',
            'status' => 'paid',
            'reference' => 'AUTO-123456',
        ]);
        $withdrawalHtml = (new WithdrawalProcessed($withdrawal))->render();
        $this->assertStringContainsString('100.000', $withdrawalHtml);
        $this->assertStringContainsString('đã được chuyển', $withdrawalHtml);

        $withdrawal->update(['status' => 'rejected', 'reason' => 'Tên không khớp']);
        $rejectHtml = (new WithdrawalProcessed($withdrawal->fresh()))->render();
        $this->assertStringContainsString('bị từ chối', $rejectHtml);
        $this->assertStringContainsString('Tên không khớp', $rejectHtml);
    }

    public function test_complaint_and_statement_mail_templates_changed_by_this_work_render(): void
    {
        $complaint = Complaint::create([
            'user_id' => $this->customer->id, 'direction' => 'customer_to_owner', 'against_user_id' => $this->owner->id,
            'category' => 'field_mismatch', 'content' => 'Sân không đúng mô tả.', 'status' => 'resolved', 'resolution' => 'Hoàn một phần.',
            'refund_amount' => 20000, 'attendance_confirmed' => true, 'trust_penalty' => 10, 'respond_by' => now()->addDay(),
        ])->load(['user', 'against']);

        $this->assertStringContainsString('một phản hồi', (new ComplaintFiled($complaint))->render());
        $mine = (new ComplaintHandled($complaint))->render();
        $this->assertStringContainsString('20.000', $mine);
        $respondent = (new ComplaintHandled($complaint, asRespondent: true))->render();
        $this->assertStringContainsString('Khiếu nại được chấp nhận', $respondent);

        $statement = OwnerStatement::create([
            'owner_profile_id' => $this->owner->fieldOwnerProfile->id, 'period' => '2026-09', 'booking_count' => 1, 'gmv' => 200000,
            'collected_amount' => 60000, 'refunded_amount' => 0, 'commission_amount' => 16000, 'payout_amount' => 44000,
            'status' => 'paid', 'transfer_code' => 'TEST',
        ]);
        $this->assertStringContainsString('ví', (new StatementReminder($statement, 'issued'))->render());
    }
}
