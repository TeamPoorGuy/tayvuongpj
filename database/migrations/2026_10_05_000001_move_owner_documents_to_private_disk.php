<?php

use App\Models\FieldOwnerProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Move CCCD/license scans out of the web-readable public disk. */
    public function up(): void
    {
        FieldOwnerProfile::whereNotNull('verification_documents')->each(function ($profile) {
            $old = $profile->verification_documents;
            if (! str_starts_with($old, '/storage/')) {
                return;
            }
            $path = substr($old, strlen('/storage/'));
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('local')->put($path, Storage::disk('public')->get($path));
                Storage::disk('public')->delete($path);
            }
            $profile->forceFill(['verification_documents' => $path])->save();
        });
    }

    public function down(): void {}
};
