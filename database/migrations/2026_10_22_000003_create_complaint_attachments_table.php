<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ảnh chứng cứ của khiếu nại, lưu ở disk private và chỉ tải được qua route có kiểm quyền.
        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->timestamps();

            $table->index(['complaint_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_attachments');
    }
};
