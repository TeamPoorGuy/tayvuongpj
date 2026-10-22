<?php

namespace Tests\Feature\Api;

use App\Mail\StatementReminder;
use App\Models\Booking;
use App\Models\FieldOwnerProfile;
use App\Models\FieldType;
use App\Models\OwnerStatement;
use App\Models\Payment;
use App\Models\SportCategory;
use App\Models\SportsField;
use App\Models\User;
use App\Services\StatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Đối soát thu hộ: SportHub chi trả cho chủ sân = tiền đã thu − hoàn cho khách − hoa hồng. */
class PayoutStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $customer;

    private SportsField $field;

    private string $period;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->period = now()->subMonth()->format('Y-m');
    }

    /** Đơn của kỳ trước, đã trả cọc 60k qua SportHub (tổng 200k, hoa hồng 8%). */
    private function paidBooking(array $attributes = [], bool $viaPlatform = true): Booking
    {
        $payment = $viaPlatform ? Payment::create([
            'user_id' => $this->customer->id, 'code' => 'SH'.Str::upper(Str::random(6)), 'option' => 'deposit',
            'total' => 200000, 'amount' => 60000, 'status' => 'paid', 'due_at' => now(), 'paid_at' => now(),
        ]) : null;

        return Booking::create($attributes + [
            'user_id' => $this->customer->id, 'sports_field_id' => $this->field->id, 'payment_id' => $payment?->id,
            'booking_date' => now()->subMonth()->startOfMonth()->addDays(5)->toDateString(),
            'start_time' => '18:00', 'end_time' => '20:00', 'total_price' => 200000,
            'payment_status' => 'paid', 'payment_option' => 'deposit', 'payment_amount' => 60000, 'commission_rate' => 8,
        ]);
    }

    private function seedPeriod(): void
    {
        // Hoàn tất có check-in: hoa hồng 8% × 200k = 16k → chi trả 44k.
        $this->paidBooking(['status' => 'completed', 'checked_in_at' => now()->subMonth()]);
        // Khách huỷ, hoàn 50% = 30k: hoa hồng 8% × 30k giữ lại = 2,4k → chi trả 27,6k.
        $this->paidBooking(['status' => 'cancelled', 'refund_status' => 'pending', 'refund_amount' => 30000]);
        // Đơn kiểu cũ khách trả thẳng chủ sân: không có tiền thu hộ, không vào bảng chi trả.
        $this->paidBooking(['status' => 'completed', 'checked_in_at' => now()->subMonth()], viaPlatform: false);
    }

    public function test_generate_computes_collected_refunded_commission_and_payout_idempotently(): void
    {
        $this->seedPeriod();
        $service = app(StatementService::class);

        $service->generate($this->period);
        $service->generate($this->period);

        $statement = OwnerStatement::sole();
        $this->assertSame(2, $statement->booking_count);
        $this->assertEquals(120000, $statement->collected_amount);
        $this->assertEquals(30000, $statement->refunded_amount);
        $this->assertEquals(18400, $statement->commission_amount);
        $this->assertEquals(71600, $statement->payout_amount);
        $this->assertSame('unpaid', $statement->status);
    }

    public function test_period_without_platform_money_is_closed_automatically(): void
    {
        $this->paidBooking(['status' => 'completed', 'checked_in_at' => now()->subMonth()], viaPlatform: false);

        app(StatementService::class)->generate($this->period);

        $statement = OwnerStatement::sole();
        $this->assertEquals(0, $statement->payout_amount);
        $this->assertSame('paid', $statement->status);
    }

    public function test_admin_pays_out_with_owner_bank_qr_then_owner_is_notified(): void
    {
        Mail::fake();
        $this->seedPeriod();
        app(StatementService::class)->generate($this->period);
        $statement = OwnerStatement::sole();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $list = $this->getJson('/api/v1/admin/statements')->assertOk();
        $this->assertEquals(71600, $list->json('meta.totals.unpaid_amount'));
        $this->assertStringContainsString('970436-1234567890', $list->json('data.0.payout_bank.qr_url'));
        $this->assertStringContainsString('amount=71600', $list->json('data.0.payout_bank.qr_url'));

        $this->postJson("/api/v1/admin/statements/{$statement->id}/payout", ['payout_ref' => 'FT26102100'])
            ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.payout_ref', 'FT26102100')
            ->assertJsonPath('data.payout_bank.qr_url', null);
        $this->postJson("/api/v1/admin/statements/{$statement->id}/payout")->assertStatus(409);

        $this->assertDatabaseHas('admin_logs', ['action' => 'payout_statement']);
        Mail::assertQueued(StatementReminder::class, fn ($m) => $m->kind === 'paid_out' && $m->hasTo($this->owner->email));
        $this->assertEquals(71600, $this->getJson('/api/v1/admin/statements')->json('meta.totals.paid_amount'));
    }

    public function test_owner_sees_own_statement_and_current_month_estimate(): void
    {
        $this->seedPeriod();
        app(StatementService::class)->generate($this->period);
        // Đơn tháng này đã thanh toán qua SportHub, chưa diễn ra.
        $this->paidBooking(['status' => 'confirmed', 'booking_date' => now()->addDay()->toDateString()]);

        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/field-owner/statements')->assertOk()
            ->assertJsonPath('data.0.payout_amount', 71600)
            ->assertJsonPath('meta.totals.unpaid_amount', 71600);

        $summary = $this->getJson('/api/v1/field-owner/statements/current-summary')->assertOk()->json('data');
        $this->assertEquals(60000, $summary['collected']);
        $this->assertEquals(16000, $summary['estimated_commission']);
        $this->assertEquals(44000, $summary['estimated_payout']);
    }
}
