<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Tiền thu hộ về ví chủ sân: sau hạn kháng cáo, trừ hoàn tiền và hoa hồng, không cộng trùng, giữ khi khách khiếu nại. */
class OwnerEarningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $customer;

    private SportsField $field;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->owner = User::factory()->create(['role' => 'customer', 'is_active' => true, 'email_verified_at' => now()]);
        FieldOwnerProfile::create([
            'user_id' => $this->owner->id, 'owner_name' => 'Chủ', 'owner_id_number' => '012345678901', 'business_name' => 'CLB',
            'business_address' => 'HN', 'business_phone' => '0900000009', 'verification_status' => 'approved', 'verified_at' => now(),
            'bank_name' => 'Vietcombank', 'bank_bin' => '970436', 'bank_account_number' => '1234567890', 'bank_account_holder' => 'NGUYEN CHU SAN',
        ]);
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id, 'field_type_id' => $type->id, 'name' => 'Sân A', 'slug' => 'san-a',
            'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
    }

    /** Đơn 18:00–20:00 đã chơi xong cách đây $hoursAgo giờ (tính từ lúc kết thúc), cọc 60k trên 200k, hoa hồng 8%. */
    private function played(float $hoursAgo, array $attributes = []): Booking
    {
        $end = now()->subMinutes((int) ($hoursAgo * 60));
        $payment = Payment::create([
            'user_id' => $this->customer->id, 'code' => 'SH'.Str::upper(Str::random(6)), 'option' => 'deposit',
            'total' => 200000, 'amount' => 60000, 'status' => 'paid', 'due_at' => now(), 'paid_at' => now(),
        ]);

        return Booking::create($attributes + [
            'user_id' => $this->customer->id, 'sports_field_id' => $this->field->id, 'payment_id' => $payment->id,
            'booking_date' => $end->copy()->subHours(2)->toDateString(),
            'start_time' => $end->copy()->subHours(2)->format('H:i'), 'end_time' => $end->format('H:i'),
            'total_price' => 200000, 'status' => 'completed', 'checked_in_at' => $end->copy()->subHours(2),
            'payment_status' => 'paid', 'payment_option' => 'deposit', 'payment_amount' => 60000, 'commission_rate' => 8,
        ]);
    }

    private function release(): void
    {
        $this->artisan('wallet:release-earnings')->assertSuccessful();
    }

    public function test_finished_booking_pays_the_owner_wallet_after_the_appeal_window_exactly_once(): void
    {
        $early = $this->played(47);
        $due = $this->played(49);

        $this->release();
        $this->release();

        // 60k đã thu − hoa hồng 8% × 200k = 44k.
        $this->assertEquals(44000, $this->owner->fresh()->wallet_balance);
        $this->assertNull($early->fresh()->earning_released_at);
        $this->assertEquals(44000, $due->fresh()->earning_amount);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $this->owner->id, 'booking_id' => $due->id, 'type' => 'owner_earning', 'amount' => 44000]);
        $this->assertSame(1, WalletTransaction::where('type', 'owner_earning')->count());
    }

    public function test_refunded_and_cancelled_bookings_only_pay_the_retained_part(): void
    {
        // Khách huỷ muộn, hoàn 50% = 30k: chủ sân giữ 30k − hoa hồng 8% × 30k = 27,6k.
        $this->played(49, ['status' => 'cancelled', 'refund_status' => 'done', 'refund_amount' => 30000, 'checked_in_at' => null]);
        // Chủ sân huỷ, hoàn đủ: không có gì về ví.
        $this->played(49, ['status' => 'cancelled', 'refund_status' => 'done', 'refund_amount' => 60000, 'checked_in_at' => null]);

        $this->release();

        $this->assertEquals(27600, $this->owner->fresh()->wallet_balance);
        $this->assertSame(2, Booking::whereNotNull('earning_released_at')->count());
    }

    public function test_bookings_with_an_open_customer_complaint_wait_but_owner_complaints_do_not_block(): void
    {
        $disputed = $this->played(49);
        Complaint::create(['user_id' => $this->customer->id, 'direction' => 'customer_to_owner', 'against_user_id' => $this->owner->id, 'booking_id' => $disputed->id, 'category' => 'field_mismatch', 'content' => 'Đèn sân bị hỏng.', 'status' => 'open']);
        $ownerComplained = $this->played(49);
        Complaint::create(['user_id' => $this->owner->id, 'direction' => 'owner_to_customer', 'against_user_id' => $this->customer->id, 'booking_id' => $ownerComplained->id, 'category' => 'property_damage', 'content' => 'Khách làm vỡ đèn.', 'status' => 'open']);

        $this->release();

        $this->assertNull($disputed->fresh()->earning_released_at);
        $this->assertNotNull($ownerComplained->fresh()->earning_released_at);
        $this->assertEquals(44000, $this->owner->fresh()->wallet_balance);

        // Khiếu nại bị từ chối thì đơn được cộng ở lần chạy sau.
        Complaint::where('booking_id', $disputed->id)->update(['status' => 'rejected']);
        $this->release();
        $this->assertEquals(88000, $this->owner->fresh()->wallet_balance);
    }

    public function test_a_refund_after_the_money_reached_the_owner_adjusts_the_wallet_and_may_go_negative(): void
    {
        $booking = $this->played(49);
        $this->release();
        $this->assertEquals(44000, $this->owner->fresh()->wallet_balance);

        // Admin chấp nhận khiếu nại muộn, hoàn 60k (toàn bộ số khách đã trả): chủ sân phải trả lại phần đã nhận.
        $complaint = Complaint::create(['user_id' => $this->customer->id, 'direction' => 'customer_to_owner', 'against_user_id' => $this->owner->id, 'booking_id' => $booking->id, 'category' => 'field_mismatch', 'content' => 'Sân không đúng mô tả.', 'status' => 'open']);
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/v1/admin/complaints/{$complaint->id}/handle", ['status' => 'resolved', 'resolution' => 'Hoàn đủ.', 'refund_amount' => 60000])->assertOk();

        $this->assertEquals(0, $booking->fresh()->earning_amount);
        $this->assertEquals(0, $this->owner->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $this->owner->id, 'type' => 'owner_adjustment', 'amount' => -44000]);
    }

    public function test_wallet_shows_money_that_is_about_to_arrive_to_the_owner(): void
    {
        $this->played(10); // chưa tới hạn kháng cáo
        Sanctum::actingAs($this->owner);

        $this->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.balance', 0)->assertJsonPath('data.pending_earnings', 44000);

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.pending_earnings', null);
    }
}
