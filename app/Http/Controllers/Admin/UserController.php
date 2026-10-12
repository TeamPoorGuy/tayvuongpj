<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        $users = User::with('fieldOwnerProfile')
            ->where('role', '!=', User::ROLE_ADMIN)
            ->when($request->input('keyword'), fn ($query, $keyword) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->orWhere('phone', 'like', "%{$keyword}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function toggle(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Không thể khóa tài khoản quản trị viên.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Đã thay đổi trạng thái tài khoản.');
    }
}
