<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sports_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('field_type_id')->constrained('field_types')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price_per_hour', 12, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'inactive'])->default('pending');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sports_fields');
    }
};
