<?php

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueRequest extends FormRequest
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
            'director_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'engineer_id' => [
                'nullable',
                'integer',
                Rule::exists('engineers', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['sometimes', 'required', 'string', Rule::in(Issue::STATUSES)],
            'is_managed' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
