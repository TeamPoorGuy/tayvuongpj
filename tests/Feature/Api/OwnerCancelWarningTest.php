<?php

namespace Tests\Feature\Api;

use App\Mail\Notice;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Chủ sân huỷ đơn đã thanh toán: mail báo số lần trong 30 ngày, từ 3 lần thì hiện ở việc chờ của admin. */
class OwnerCancelWarningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    private User $admin;

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

    /** Đơn đã xác nhận, khách đã trả đủ 200k qua SportHub, giờ chơi ở tương lai. */
    private function paidBooking(int $dayOffset, bool $paid = true): Booking
    {
        $payment = Payment::create([
            'user_id' => $this->customer->id, 'code' => 'SH'.Str::upper(Str::random(6)), 'option' => 'full',
            'total' => 200000, 'amount' => 200000, 'status' => $paid ? 'paid' : 'awaiting', 'due_at' => now()->addDay(), 'paid_at' => $paid ? now() : null,
        ]);

        return Booking::create([
            'user_id' => $this->customer->id, 'sports_field_id' => $this->field->id, 'payment_id' => $payment->id,
            'booking_date' => now()->addDays($dayOffset)->toDateString(), 'start_time' => '18:00', 'end_time' => '20:00',
            'total_price' => 200000, 'status' => $paid ? 'confirmed' : 'pending', 'payment_status' => $paid ? 'paid' : 'awaiting',
            'payment_option' => 'full', 'payment_amount' => 200000, 'commission_rate' => 8,
        ]);
    }

    private function ownerCancels(Booking $booking)
    {
        Sanctum::actingAs($this->owner->fresh());

        return $this->patchJson("/api/v1/field-owner/bookings/{$booking->id}/status", ['status' => 'cancelled', 'reason' => 'Sân bị sự cố.']);
    }

    private function queue(): array
    {
        Sanctum::actingAs($this->admin);

        return $this->getJson('/api/v1/admin/dashboard')->assertOk()->json('data.action_queue');
    }

    public function test_owner_is_told_how_many_paid_bookings_they_cancelled_and_admin_sees_them_from_three(): void
    {
        foreach ([1, 2] as $day) {
            $this->ownerCancels($this->paidBooking($day))->assertOk();
        }

        Mail::assertQueued(Notice::class, fn (Notice $mail) => $mail->hasTo($this->owner->email) && str_contains($mail->lines[0], 'huỷ 2 đơn') && count($mail->lines) === 1);
        $this->assertSame(0, $this->queue()['frequent_cancel_owners']);

        $this->ownerCancels($this->paidBooking(3))->assertOk();

        Mail::assertQueued(Notice::class, fn (Notice $mail) => str_contains($mail->lines[0], 'huỷ 3 đơn') && count($mail->lines) === 2);
        $this->assertSame(1, $this->queue()['frequent_cancel_owners']);
    }

    public function test_admin_can_filter_owners_by_frequent_cancellations(): void
    {
        foreach ([1, 2, 3] as $day) {
            $this->ownerCancels($this->paidBooking($day))->assertOk();
        }
        Sanctum::actingAs($this->admin);

        $all = $this->getJson('/api/v1/admin/field-owners')->assertOk();
        $this->assertSame(3, $all->json('data.0.cancellations_30d'));
        $this->getJson('/api/v1/admin/field-owners?frequent_cancel=1')->assertOk()->assertJsonCount(1, 'data');

        // Chủ sân khác không huỷ đơn nào thì không lọt vào bộ lọc.
        $other = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        FieldOwnerProfile::create([
            'user_id' => $other->id, 'owner_name' => 'Khác', 'owner_id_number' => '012345678902', 'business_name' => 'CLB B',
            'business_address' => 'HN', 'business_phone' => '0900000008', 'verification_status' => 'approved', 'verified_at' => now(),
        ]);
        $this->getJson('/api/v1/admin/field-owners')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/field-owners?frequent_cancel=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cancelling_an_unpaid_hold_or_an_old_cancellation_does_not_count(): void
    {
        // Lượt giữ chỗ chưa trả tiền: không có gì để hoàn nên không tính và không gửi cảnh báo.
        $this->ownerCancels($this->paidBooking(1, paid: false));
        Mail::assertNotQueued(Notice::class);

        // Huỷ cách đây hơn 30 ngày không tính.
        foreach ([1, 2, 3] as $day) {
            $old = $this->paidBooking($day);
            $old->markCancelled('field_owner', 'Huỷ cũ');
            $old->update(['cancelled_at' => now()->subDays(40)]);
        }

        $this->assertSame(0, $this->queue()['frequent_cancel_owners']);
    }
}
