<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkIssueGroupRequest extends FormRequest
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
            'issue_ids' => ['required', 'array', 'min:1'],
            'issue_ids.*' => ['integer', 'distinct', Rule::exists('issues', 'id')],
            'group_id' => ['present', 'nullable', 'integer', Rule::exists('groups', 'id')],
        ];
    }
}
