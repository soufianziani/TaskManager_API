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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'task_name' => ['nullable', 'string', 'max:255'],
            'period_type' => ['nullable', 'string', 'max:255'],
            'period_start' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'period_end' => ['nullable', 'date_format:Y-m-d H:i:s', 'after_or_equal:period_start'],
            'time_cloture' => ['nullable', 'string'], // mediumtext - can be JSON or single datetime string
            'time_out' => ['nullable', 'string'], // mediumtext - can be JSON or single string
            'period_days' => ['nullable', 'string'],
            'period_urgent' => ['nullable', 'string'],
            'type_justif' => ['nullable', 'string'],
            'users' => ['nullable', 'string', 'max:255'],
            'step' => ['nullable', 'string', 'max:255', 'in:pending,in_progress,completed'],
            'file' => ['nullable', 'string', 'max:255'], // File ID from files table
            'controller' => ['nullable', 'string', 'max:255'], // Controller user ID or name
            'alarm' => ['nullable', 'string'], // Alarm times as JSON string
            // justif_file is not included - always set to null when creating
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $periodStart = $this->input('period_start');
            $periodEnd = $this->input('period_end');
            $timeCloture = $this->input('time_cloture');

            // Validate time_cloture constraints
            // time_cloture can be a JSON string (for different times per day) or a single datetime string
            if ($timeCloture) {
                try {
                    // Try to parse as JSON first
                    $timeClotureData = json_decode($timeCloture, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && is_array($timeClotureData)) {
                        // It's JSON - validate each datetime value
                        foreach ($timeClotureData as $day => $datetimeStr) {
                            if (is_string($datetimeStr)) {
                                $timeClotureDate = \Carbon\Carbon::parse($datetimeStr);
                                
                                // Check: period_start < time_cloture
                                if ($periodStart) {
                                    $periodStartDate = \Carbon\Carbon::parse($periodStart);
                                    if ($timeClotureDate->lte($periodStartDate)) {
                                        $validator->errors()->add(
                                            'time_cloture',
                                            "Time cloture for {$day} must be after period start."
                                        );
                                    }
                                }
                                
                                // Check: time_cloture <= period_end
                                if ($periodEnd) {
                                    $periodEndDate = \Carbon\Carbon::parse($periodEnd);
                                    if ($timeClotureDate->gt($periodEndDate)) {
                                        $validator->errors()->add(
                                            'time_cloture',
                                            "Time cloture for {$day} must be less than or equal to period end."
                                        );
                                    }
                                }
                            }
                        }
                    } else {
                        // It's a single datetime string
                        $timeClotureDate = \Carbon\Carbon::parse($timeCloture);
                        
                        // Check: period_start < time_cloture
                        if ($periodStart) {
                            $periodStartDate = \Carbon\Carbon::parse($periodStart);
                            if ($timeClotureDate->lte($periodStartDate)) {
                                $validator->errors()->add(
                                    'time_cloture',
                                    'Time cloture must be after period start.'
                                );
                            }
                        }
                        
                        // Check: time_cloture <= period_end
                        if ($periodEnd) {
                            $periodEndDate = \Carbon\Carbon::parse($periodEnd);
                            if ($timeClotureDate->gt($periodEndDate)) {
                                $validator->errors()->add(
                                    'time_cloture',
                                    'Time cloture must be less than or equal to period end.'
                                );
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $validator->errors()->add(
                        'time_cloture',
                        'Invalid time cloture format. Must be a valid datetime string or JSON object.'
                    );
                }
            }
        });
    }
}
