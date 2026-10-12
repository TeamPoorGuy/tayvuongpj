<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FieldType;
use App\Models\SportCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SportCategoryController extends Controller
{
    public function index()
    {
        $categories = SportCategory::with('fieldTypes')
            ->withCount('fieldTypes')
            ->latest()
            ->get();

        return view('admin.sport-categories.index', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:sport_categories,name'],
            'icon' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
        ]);

        SportCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'icon' => $data['icon'] ?? '⚽',
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Thêm danh mục thể thao thành công.');
    }

    public function storeType(Request $request)
    {
        $data = $request->validate([
            'sport_category_id' => ['required', 'integer', 'exists:sport_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        FieldType::create([
            'sport_category_id' => $data['sport_category_id'],
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Thêm loại sân thành công.');
    }
}
