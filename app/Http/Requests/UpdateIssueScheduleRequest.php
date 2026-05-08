<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIssueScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'planned_start' => ['nullable', 'date_format:Y-m-d'],
            'actual_start' => ['nullable', 'date_format:Y-m-d'],
            'planned_end' => ['nullable', 'date_format:Y-m-d'],
            'actual_end' => ['nullable', 'date_format:Y-m-d'],
            'planned_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'actual_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ];
    }
}
