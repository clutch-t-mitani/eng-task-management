<?php

namespace App\Http\Requests;

use App\Constants\IssueFilters;
use App\Enums\IssueStatus;
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
        foreach (['product_id', 'engineer_id', 'director_id', 'status_id'] as $key) {
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
            'engineer_id.*' => [
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === IssueFilters::EMPTY_FILTER_VALUE) {
                        return;
                    }

                    $validator = validator(
                        [$attribute => $value],
                        [$attribute => ['integer', 'exists:engineers,id']],
                    );

                    if ($validator->fails()) {
                        $fail('選択されたエンジニアは正しくありません。');
                    }
                },
            ],
            'director_id' => ['nullable', 'array'],
            'director_id.*' => [
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === IssueFilters::EMPTY_FILTER_VALUE) {
                        return;
                    }

                    $validator = validator(
                        [$attribute => $value],
                        [$attribute => ['integer', 'exists:users,id']],
                    );

                    if ($validator->fails()) {
                        $fail('選択されたディレクターは正しくありません。');
                    }
                },
            ],
            'status_id' => ['nullable', 'array'],
            'status_id.*' => ['integer', Rule::in(IssueStatus::values())],
            'is_managed' => ['nullable', 'boolean'],
            'unmanaged_imports' => ['nullable', 'boolean'],
            'planned_start_from' => ['nullable', 'date_format:Y-m-d'],
            'planned_start_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:planned_start_from'],
            'planned_end_from' => ['nullable', 'date_format:Y-m-d'],
            'planned_end_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:planned_end_from'],
            'actual_start_from' => ['nullable', 'date_format:Y-m-d'],
            'actual_start_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:actual_start_from'],
            'actual_end_from' => ['nullable', 'date_format:Y-m-d'],
            'actual_end_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:actual_end_from'],
            'flags' => ['nullable', 'array'],
            'flags.*' => ['string', Rule::in(['overdue', 'due_soon', 'none'])],
        ];
    }
}
