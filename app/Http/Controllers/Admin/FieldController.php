<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SportsField;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in([
                SportsField::STATUS_PENDING,
                SportsField::STATUS_APPROVED,
                SportsField::STATUS_REJECTED,
                SportsField::STATUS_INACTIVE,
            ])],
        ]);

        $fields = SportsField::with(['owner.fieldOwnerProfile', 'fieldType.sportCategory', 'primaryImage', 'reviews'])
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.fields.index', compact('fields'));
    }

    public function verify(Request $request, SportsField $field)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                SportsField::STATUS_APPROVED,
                SportsField::STATUS_REJECTED,
            ])],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:500'],
        ]);

        $field->update([
            'status' => $data['status'],
            'rejection_reason' => $data['status'] === SportsField::STATUS_REJECTED ? ($data['rejection_reason'] ?? null) : null,
        ]);

        return back()->with('success', 'Đã xử lý trạng thái sân.');
    }
}
