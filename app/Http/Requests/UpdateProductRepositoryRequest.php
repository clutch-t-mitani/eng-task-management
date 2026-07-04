<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRepositoryRequest extends FormRequest
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
            'owner' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'repo' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
        ];
    }
}
