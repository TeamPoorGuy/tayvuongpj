<?php

namespace App\Http\Controllers\FieldOwner;

use App\Http\Controllers\Controller;
use App\Models\FieldImage;
use App\Models\FieldType;
use App\Models\SportsField;
use App\Models\TimeSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FieldController extends Controller
{
    public function index(Request $request)
    {
        $fields = SportsField::where('field_owner_id', $request->user()->id)
            ->with(['fieldType.sportCategory', 'primaryImage', 'reviews'])
            ->withCount('bookings')
            ->latest()
            ->paginate(10);

        return view('field-owner.fields.index', compact('fields'));
    }

    public function create()
    {
        $fieldTypes = FieldType::query()
            ->where('is_active', true)
            ->with('sportCategory')
            ->get();

        return view('field-owner.fields.create', compact('fieldTypes'));
    }

    public function store(Request $request)
    {
        $data = $this->validateField($request);

        DB::transaction(function () use ($request, $data) {
            $field = SportsField::create([
                'field_owner_id' => $request->user()->id,
                'field_type_id' => $data['field_type_id'],
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
                'address' => $data['address'],
                'description' => $data['description'] ?? null,
                'price_per_hour' => $data['price_per_hour'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'status' => SportsField::STATUS_PENDING,
                'is_active' => true,
            ]);

            foreach ($request->file('images', []) as $index => $image) {
                FieldImage::create([
                    'sports_field_id' => $field->id,
                    'image_path' => '/storage/'.$image->store('fields', 'public'),
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }

            foreach (TimeSlot::DEFAULT_SCHEDULE as [$start, $end]) {
                TimeSlot::create([
                    'sports_field_id' => $field->id,
                    'start_time' => $start,
                    'end_time' => $end,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()->route('field-owner.fields.index')
            ->with('success', 'Đã thêm sân mới. Sân đang chờ admin kiểm duyệt.');
    }

    public function edit(Request $request, SportsField $field)
    {
        abort_unless($field->field_owner_id === $request->user()->id, 403);
        $field->load('images');
        $fieldTypes = FieldType::query()
            ->where('is_active', true)
            ->with('sportCategory')
            ->get();

        return view('field-owner.fields.edit', compact('field', 'fieldTypes'));
    }

    public function update(Request $request, SportsField $field)
    {
        if ($field->field_owner_id !== $request->user()->id) {
            return back()->with('error', 'Bạn không có quyền sửa sân này.');
        }

        $data = $this->validateField($request);
        $field->update([
            'name' => $data['name'],
            'field_type_id' => $data['field_type_id'],
            'address' => $data['address'],
            'price_per_hour' => $data['price_per_hour'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('field-owner.fields.index')->with('success', 'Cập nhật sân thành công.');
    }

    public function toggle(Request $request, SportsField $field)
    {
        if ($field->field_owner_id !== $request->user()->id) {
            return back()->with('error', 'Bạn không có quyền thay đổi sân này.');
        }

        $field->update(['is_active' => ! $field->is_active]);

        return back()->with('success', 'Đã thay đổi trạng thái hoạt động của sân.');
    }

    private function validateField(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'field_type_id' => ['required', 'integer', 'exists:field_types,id'],
            'address' => ['required', 'string', 'max:255'],
            'price_per_hour' => ['required', 'numeric', 'gt:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);
    }
}
