<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:100', 'in:UI,NUI,UNI,NUNI'],
            'status' => ['nullable', 'boolean'],
            'url' => ['nullable', 'string', 'max:255'],
            'redirect' => ['nullable', 'boolean'],
            'department' => ['nullable', 'string'],
            'period_type' => ['nullable', 'string', 'max:255'],
            'period_start' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'period_end' => ['nullable', 'date_format:Y-m-d H:i:s', 'after_or_equal:period_start'],
            'period_days' => ['nullable', 'string'],
            'period_urgent' => ['nullable', 'string'],
            'type_justif' => ['nullable', 'string'],
            'users' => ['nullable', 'string', 'max:255'],
            'step' => ['nullable', 'string', 'max:255', 'in:pending,in_progress,completed'],
            'file' => ['nullable', 'string', 'max:255'], // File ID from files table
            // justif_file is not included - always set to null when creating
        ];
    }
}
