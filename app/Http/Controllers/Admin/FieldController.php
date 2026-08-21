<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SportsField;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function index(Request $request)
    {
        $query = SportsField::with(['owner.fieldOwnerProfile', 'fieldType.sportCategory', 'primaryImage']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $fields = $query->latest()->paginate(15)->withQueryString();

        return view('admin.fields.index', compact('fields'));
    }

    public function verify(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ]);

        $field = SportsField::findOrFail($id);
        $field->update([
            'status' => $request->status,
        ]);

        $actionText = $request->status === 'approved' ? 'duyệt' : 'từ chối';
        return back()->with('success', "Đã {$actionText} sân {$field->name}.");
    }
}
