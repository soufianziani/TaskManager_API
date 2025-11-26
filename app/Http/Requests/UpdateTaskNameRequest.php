<?php

namespace App\Http\Requests;

use App\Models\TaskName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskNameRequest extends FormRequest
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
        $taskNameId = $this->route('id');
        $existingTaskName = $taskNameId ? TaskName::find($taskNameId) : null;
        $categoryId = $this->category_id ?? $existingTaskName?->category_id;

        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                $categoryId
                    ? Rule::unique('task_name')
                        ->where(fn($query) => $query->where('category_id', $categoryId))
                        ->ignore($taskNameId)
                    : Rule::unique('task_name')->ignore($taskNameId),
            ],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string'],
            'permission' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

