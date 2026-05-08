<?php

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['product_id', 'engineer_id', 'director_id', 'status'] as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->query($key);
            $values = is_array($value) ? $value : [$value];
            $values = array_values(array_filter($values, fn (mixed $item): bool => $item !== null && $item !== ''));

            $this->merge([$key => $values]);
        }

        foreach (['is_managed', 'unmanaged_imports'] as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->query($key);
            if ($value === 'true') {
                $this->merge([$key => true]);
            }

            if ($value === 'false') {
                $this->merge([$key => false]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'array'],
            'product_id.*' => ['integer', 'exists:products,id'],
            'engineer_id' => ['nullable', 'array'],
            'engineer_id.*' => ['integer', 'exists:engineers,id'],
            'director_id' => ['nullable', 'array'],
            'director_id.*' => ['integer', 'exists:users,id'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(Issue::STATUSES)],
            'is_managed' => ['nullable', 'boolean'],
            'unmanaged_imports' => ['nullable', 'boolean'],
        ];
    }
}
