<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        return [
            'phone_number' => ['required', 'string', 'unique:users,phone_number'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string'],
            'support_number' => ['nullable', 'string'],
            'device_id' => ['nullable', 'string'],
            'fcm_token' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'unique:users,email'],
        ];
    }
}
