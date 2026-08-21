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
        $categories = SportCategory::with('fieldTypes')->latest()->get();
        return view('admin.sport-categories.index', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:sport_categories,name'],
            'icon' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        SportCategory::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'icon' => $request->icon ?? '⚽',
            'description' => $request->description,
            'is_active' => true,
        ]);

        return back()->with('success', 'Thêm danh mục thể thao mới thành công.');
    }

    public function storeType(Request $request)
    {
        $request->validate([
            'sport_category_id' => ['required', 'exists:sport_categories,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        FieldType::create([
            'sport_category_id' => $request->sport_category_id,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'is_active' => true,
        ]);

        return back()->with('success', 'Thêm loại sân mới thành công.');
    }
}
