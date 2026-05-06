<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ordered_ids' => '並び順',
            'ordered_ids.*' => '並び順のID',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attributeは必ず指定してください。',
            'array' => ':attributeは配列で指定してください。',
            'min' => ':attributeは:min件以上指定してください。',
            'integer' => ':attributeは整数で指定してください。',
            'distinct' => ':attributeが重複しています。',
        ];
    }
}
