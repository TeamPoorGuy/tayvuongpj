<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\ComplaintAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintAttachmentController extends Controller
{
    /** Chỉ hai bên của khiếu nại và admin xem được ảnh chứng cứ. */
    public function show(Request $request, ComplaintAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        $complaint = $attachment->complaint;
        if (! $user->isAdmin() && $user->id !== $complaint->user_id && $user->id !== $complaint->against_user_id) {
            throw new ForbiddenException('Bạn không có quyền xem ảnh chứng cứ này.');
        }
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name);
    }
}
