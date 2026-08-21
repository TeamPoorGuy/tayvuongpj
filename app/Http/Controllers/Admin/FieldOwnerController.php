<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldOwnerProfile;
use Illuminate\Http\Request;

class FieldOwnerController extends Controller
{
    public function index()
    {
        $profiles = FieldOwnerProfile::with('user')
            ->latest()
            ->paginate(10);

        return view('admin.field-owners.index', compact('profiles'));
    }

    public function verify(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $profile = FieldOwnerProfile::findOrFail($id);
        $profile->update([
            'verification_status' => $request->status,
        ]);

        $actionText = $request->status === 'approved' ? 'duyệt' : 'từ chối';
        return back()->with('success', "Đã {$actionText} hồ sơ chủ sân {$profile->business_name}.");
    }
}
