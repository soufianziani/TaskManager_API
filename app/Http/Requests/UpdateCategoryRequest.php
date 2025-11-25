<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->route('id');
        $existingCategory = $categoryId ? Category::find($categoryId) : null;
        $departmentId = $this->department_id ?? $existingCategory?->department_id;

        return [
            'department_id' => ['sometimes', 'exists:departments,id'],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                $departmentId
                    ? Rule::unique('categories')
                        ->where(fn($query) => $query->where('department_id', $departmentId))
                        ->ignore($categoryId)
                    : Rule::unique('categories')->ignore($categoryId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'permission' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}

