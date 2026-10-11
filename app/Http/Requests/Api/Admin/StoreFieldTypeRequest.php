<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sport_category_id' => ['required', 'integer', 'exists:sport_categories,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('field_types')->where('sport_category_id', $this->input('sport_category_id'))],
            'description' => ['nullable', 'string'],
        ];
    }
}
