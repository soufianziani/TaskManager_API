<?php

namespace App\Http\Requests;

use App\Models\Type;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTypeRequest extends FormRequest
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
        $typeId = $this->route('id');
        $existingType = $typeId ? Type::find($typeId) : null;
        $departmentId = $this->department_id ?? $existingType?->department_id;

        return [
            'department_id' => ['sometimes', 'exists:departments,id'],
            'name' => [
                'sometimes',
                'string',
                'max:255',
                $departmentId
                    ? Rule::unique('types')
                        ->where(fn($query) => $query->where('department_id', $departmentId))
                        ->ignore($typeId)
                    : Rule::unique('types')->ignore($typeId),
            ],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'nullable', 'string', 'max:50'],
            'description' => ['sometimes', 'nullable', 'string'],
            'permission' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

