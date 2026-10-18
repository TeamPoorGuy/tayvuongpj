<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_profile_id')->constrained('field_owner_profiles')->cascadeOnDelete();
            $table->string('period', 7); // Định dạng YYYY-MM (ví dụ 2026-09)
            $table->unsignedInteger('booking_count')->default(0);
            $table->decimal('gmv', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('unpaid'); // unpaid, reported, paid, waived
            $table->dateTime('due_at')->nullable();
            $table->string('transfer_code', 50)->nullable();
            $table->dateTime('reported_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['owner_profile_id', 'period']);
            $table->index(['period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_statements');
    }
};
