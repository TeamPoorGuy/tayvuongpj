<?php

namespace Tests\Feature\Api;

use App\Models\Booking;
use App\Models\Complaint;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\OwnerStatement;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\TrustScoreLog;
use App\Models\User;
use App\Services\StatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Khiếu nại có hiệu lực thật: hoàn tiền, gỡ vắng mặt, giữ tiền chi trả khi đang tranh chấp. */
class ComplaintResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $customer;

    private SportsField $field;

    private string $period;

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
        $this->customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'trust_score' => 75]);
        $cat = SportCategory::create(['slug' => 'cau-long', 'name' => 'Cầu lông']);
        $type = FieldType::create(['slug' => 'san-don', 'sport_category_id' => $cat->id, 'name' => 'Sân đơn']);
        $this->field = SportsField::create([
            'field_owner_id' => $this->owner->id, 'field_type_id' => $type->id, 'name' => 'Sân A', 'slug' => 'san-a',
            'address' => '1 A, Cầu Giấy, Hà Nội', 'price_per_hour' => 100000, 'status' => 'approved', 'is_active' => true,
        ]);
        $this->period = now()->subMonth()->format('Y-m');
    }

    /** Đơn kỳ trước đã chơi xong, khách trả cọc 60k qua SportHub trên tổng 200k (hoa hồng 8%). */
    private function playedBooking(array $attributes = []): Booking
    {
        $payment = Payment::create([
            'user_id' => $this->customer->id, 'code' => 'SH'.Str::upper(Str::random(6)), 'option' => 'deposit',
            'total' => 200000, 'amount' => 60000, 'status' => 'paid', 'due_at' => now(), 'paid_at' => now(),
        ]);

        return Booking::create($attributes + [
            'user_id' => $this->customer->id, 'sports_field_id' => $this->field->id, 'payment_id' => $payment->id,
            'booking_date' => now()->subMonth()->startOfMonth()->addDays(5)->toDateString(),
            'start_time' => '18:00', 'end_time' => '20:00', 'total_price' => 200000, 'status' => 'completed',
            'checked_in_at' => now()->subMonth(), 'payment_status' => 'paid', 'payment_option' => 'deposit',
            'payment_amount' => 60000, 'commission_rate' => 8,
        ]);
    }

    private function complain(Booking $booking, string $category = 'field_mismatch'): Complaint
    {
        return Complaint::create([
            'user_id' => $this->customer->id, 'booking_id' => $booking->id, 'category' => $category,
            'content' => 'Sân không đúng mô tả, đèn hỏng.', 'status' => 'open',
        ]);
    }

    private function handle(Complaint $complaint, array $payload)
    {
        Sanctum::actingAs($this->admin);

        return $this->patchJson("/api/v1/admin/complaints/{$complaint->id}/handle", $payload);
    }

    public function test_second_active_complaint_on_same_booking_is_blocked_until_the_first_closes(): void
    {
        $booking = $this->playedBooking();
        Sanctum::actingAs($this->customer);
        $payload = ['booking_id' => $booking->id, 'category' => 'field_mismatch', 'content' => 'Đèn sân bị hỏng.'];

        $this->postJson('/api/v1/customer/complaints', $payload)->assertCreated();
        $this->postJson('/api/v1/customer/complaints', $payload)->assertStatus(409);

        $this->handle(Complaint::sole(), ['status' => 'rejected', 'resolution' => 'Không có cơ sở.'])->assertOk();
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/customer/complaints', $payload)->assertCreated();
    }

    public function test_customer_can_dispute_a_wrongful_no_show(): void
    {
        $booking = $this->playedBooking(['checked_in_at' => null, 'no_show_at' => now()->subHours(2)]);
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/customer/complaints', ['booking_id' => $booking->id, 'category' => 'wrong_no_show', 'content' => 'Tôi đã đến sân đúng giờ.'])
            ->assertCreated()->assertJsonPath('data.category', 'wrong_no_show');
        $this->getJson('/api/v1/customer/bookings')->assertOk()->assertJsonPath('data.0.complaint.status', 'open');
    }

    public function test_closed_complaint_cannot_be_reopened_and_closing_needs_a_resolution(): void
    {
        $complaint = $this->complain($this->playedBooking());

        $this->handle($complaint, ['status' => 'resolved'])->assertStatus(422)->assertJsonValidationErrors('resolution');
        $this->handle($complaint, ['status' => 'processing'])->assertOk();
        $this->handle($complaint, ['status' => 'rejected', 'resolution' => 'Đã kiểm tra, không có cơ sở.'])->assertOk();
        $this->handle($complaint, ['status' => 'resolved', 'resolution' => 'Đổi ý chấp nhận.'])->assertStatus(409);
    }

    public function test_resolved_complaint_refunds_a_completed_booking_within_what_customer_paid(): void
    {
        $booking = $this->playedBooking();
        $complaint = $this->complain($booking);

        $this->handle($complaint, ['status' => 'resolved', 'resolution' => 'Hoàn một phần.', 'refund_amount' => 70000])
            ->assertStatus(422);
        $this->assertNull($booking->refresh()->refund_status);

        $this->handle($complaint, ['status' => 'resolved', 'resolution' => 'Hoàn một phần.', 'refund_amount' => 20000])
            ->assertOk()->assertJsonPath('data.refund_amount', 20000)->assertJsonPath('data.booking.refundable', 40000);

        $booking->refresh();
        $this->assertSame('done', $booking->refund_status);
        $this->assertEquals(20000, $booking->refund_amount);
        $this->assertEquals(20000, $this->customer->refresh()->wallet_balance, 'Tiền hoàn vào thẳng ví khách.');
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $this->customer->id, 'booking_id' => $booking->id, 'type' => 'refund', 'amount' => 20000]);
        $this->assertDatabaseHas('admin_logs', ['action' => 'handle_complaint']);
    }

    public function test_refund_and_attendance_need_an_accepted_complaint_tied_to_a_booking(): void
    {
        $booking = $this->playedBooking();
        $complaint = $this->complain($booking);

        $this->handle($complaint, ['status' => 'rejected', 'resolution' => 'Không có cơ sở.', 'refund_amount' => 20000])->assertStatus(422);
        // Đơn có mặt đầy đủ, không có gì để gỡ vắng mặt.
        $this->handle($complaint, ['status' => 'resolved', 'resolution' => 'Chấp nhận.', 'confirm_attendance' => true])->assertStatus(422);
        $this->assertSame('open', $complaint->refresh()->status);

        $contact = Complaint::create(['category' => 'other', 'content' => 'Liên hệ chung.', 'subject_type' => 'contact', 'status' => 'open']);
        $this->handle($contact, ['status' => 'resolved', 'resolution' => 'Đã trả lời.', 'refund_amount' => 20000])->assertStatus(422);
    }

    public function test_attendance_confirmation_undoes_a_wrongful_no_show(): void
    {
        $booking = $this->playedBooking(['checked_in_at' => null, 'no_show_at' => now()->subMonth()]);
        $complaint = $this->complain($booking, 'bad_behavior');
        // Đơn vắng mặt chỉ tính hoa hồng trên tiền cọc.
        $this->assertEquals(60000, $booking->commissionBase());

        $this->handle($complaint, ['status' => 'resolved', 'resolution' => 'Camera xác nhận khách có mặt.', 'confirm_attendance' => true])
            ->assertOk()->assertJsonPath('data.attendance_confirmed', true);

        $booking->refresh();
        $this->assertNull($booking->no_show_at);
        $this->assertNotNull($booking->checked_in_at);
        $this->assertSame(100, $this->customer->refresh()->trust_score);
        $this->assertDatabaseHas('trust_score_logs', ['user_id' => $this->customer->id, 'booking_id' => $booking->id, 'change' => 25]);
        $this->assertSame(1, TrustScoreLog::where('booking_id', $booking->id)->count());
        // Hoa hồng quay về tính trên cả đơn, không còn chỉ trên tiền cọc của đơn vắng mặt.
        $this->assertEquals(200000, $booking->commissionBase());
    }

    public function test_disputed_booking_is_held_out_of_the_report_and_comes_back_when_the_complaint_closes(): void
    {
        $held = $this->playedBooking();
        $this->playedBooking();
        $service = app(StatementService::class);
        $service->generate($this->period);
        $this->assertSame(2, OwnerStatement::sole()->booking_count);

        // Khách khiếu nại rồi báo cáo được lập lại: đơn đang tranh chấp bị giữ lại.
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/customer/complaints', ['booking_id' => $held->id, 'category' => 'field_mismatch', 'content' => 'Đèn sân bị hỏng.'])->assertCreated();
        $service->generate($this->period);

        $statement = OwnerStatement::sole();
        $this->assertSame(1, $statement->booking_count);
        // Còn một đơn: thu hộ 60k − hoa hồng 8% × 200k = 44k về ví chủ sân.
        $this->assertEquals(44000, $statement->payout_amount);
        $this->assertNull($held->refresh()->statement_id);

        // Khiếu nại bị từ chối: lập lại thì đơn quay về báo cáo.
        $this->handle(Complaint::sole(), ['status' => 'rejected', 'resolution' => 'Không có cơ sở.'])->assertOk();
        $service->generate($this->period);
        $this->assertSame(2, OwnerStatement::sole()->booking_count);
        $this->assertSame($statement->id, $held->refresh()->statement_id);
    }

    public function test_held_booking_of_a_paid_period_joins_the_next_statement_once_the_complaint_closes(): void
    {
        $held = $this->playedBooking();
        $complaint = $this->complain($held);
        $service = app(StatementService::class);

        $service->generate($this->period);
        $this->assertSame(0, OwnerStatement::sole()->booking_count);

        $this->handle($complaint, ['status' => 'resolved', 'resolution' => 'Hoàn 20k.', 'refund_amount' => 20000])->assertOk();
        $service->generate(now()->format('Y-m'));

        $current = OwnerStatement::where('period', now()->format('Y-m'))->sole();
        $this->assertSame(1, $current->booking_count);
        $this->assertEquals(60000, $current->collected_amount);
        $this->assertEquals(20000, $current->refunded_amount);
        // Hoa hồng tính trên phần còn lại sau hoàn: (200k − 20k) × 8% = 14,4k → chi trả 60k − 20k − 14,4k.
        $this->assertEquals(14400, $current->commission_amount);
        $this->assertEquals(25600, $current->payout_amount);
    }
}
