<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\FieldImage;
use App\Models\FieldType;
use App\Models\SportsField;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FieldController extends Controller
{
    public function index()
    {
        $fields = SportsField::where('field_owner_id', Auth::id())
            ->with(['fieldType.sportCategory', 'primaryImage'])
            ->withCount('bookings')
            ->latest()
            ->paginate(10);

        return view('field-owner.fields.index', compact('fields'));
    }

    public function create()
    {
        $fieldTypes = FieldType::where('is_active', true)->with('sportCategory')->get();
        return view('field-owner.fields.create', compact('fieldTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'field_type_id' => ['required', 'exists:field_types,id'],
            'address' => ['required', 'string', 'max:255'],
            'price_per_hour' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ], [
            'price_per_hour.gt' => 'Giá thuê sân phải lớn hơn 0.',
        ]);

        $field = SportsField::create([
            'field_owner_id' => Auth::id(),
            'field_type_id' => $request->field_type_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name) . '-' . time(),
            'address' => $request->address,
            'description' => $request->description,
            'price_per_hour' => $request->price_per_hour,
            'status' => 'pending', // Chờ Admin kiểm duyệt
            'is_active' => true,
        ]);

        // Upload hình ảnh
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $idx => $file) {
                $path = $file->store('fields', 'public');
                FieldImage::create([
                    'sports_field_id' => $field->id,
                    'image_path' => '/storage/' . $path,
                    'is_primary' => $idx === 0,
                    'sort_order' => $idx,
                ]);
            }
        }

        // Tự động sinh khung giờ mặc định (06:00 đến 22:00)
        $defaultSlots = [
            ['06:00', '07:30'], ['07:30', '09:00'], ['09:00', '10:30'],
            ['14:00', '15:30'], ['15:30', '17:00'], ['17:00', '18:30'],
            ['18:30', '20:00'], ['20:00', '21:30']
        ];
        foreach ($defaultSlots as $s) {
            TimeSlot::create([
                'sports_field_id' => $field->id,
                'start_time' => $s[0],
                'end_time' => $s[1],
                'is_active' => true,
            ]);
        }

        return redirect()->route('field-owner.fields.index')->with('success', 'Đã thêm sân mới thành công! Sân đang chờ Admin kiểm duyệt.');
    }

    public function edit($id)
    {
        $field = SportsField::where('id', $id)->where('field_owner_id', Auth::id())->with('images')->firstOrFail();
        $fieldTypes = FieldType::where('is_active', true)->get();
        return view('field-owner.fields.edit', compact('field', 'fieldTypes'));
    }

    public function update(Request $request, $id)
    {
        $field = SportsField::where('id', $id)->where('field_owner_id', Auth::id())->firstOrFail();

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'field_type_id' => ['required', 'exists:field_types,id'],
            'address' => ['required', 'string', 'max:255'],
            'price_per_hour' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
        ]);

        $field->update([
            'name' => $request->name,
            'field_type_id' => $request->field_type_id,
            'address' => $request->address,
            'price_per_hour' => $request->price_per_hour,
            'description' => $request->description,
        ]);

        return redirect()->route('field-owner.fields.index')->with('success', 'Cập nhật thông tin sân thành công.');
    }

    public function toggleStatus($id)
    {
        $field = SportsField::where('id', $id)->where('field_owner_id', Auth::id())->firstOrFail();
        $field->update(['is_active' => !$field->is_active]);

        $statusText = $field->is_active ? 'hoạt động' : 'ngừng hoạt động';
        return back()->with('success', "Đã chuyển trạng thái sân thành {$statusText}.");
    }
}
