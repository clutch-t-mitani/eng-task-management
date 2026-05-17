<?php

namespace App\Http\Requests;

use App\Models\Issue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'planned_end' => ['nullable', 'date_format:Y-m-d'],
            'actual_start' => ['nullable', 'date_format:Y-m-d'],
            'actual_end' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $schedule = $this->mergedScheduleValues();

                $this->validateDateOrder(
                    $validator,
                    $schedule['planned_start'],
                    $schedule['planned_end'],
                    'planned_end',
                    '予定終了は予定開始以降の日付を指定してください。',
                );

                $this->validateDateOrder(
                    $validator,
                    $schedule['actual_start'],
                    $schedule['actual_end'],
                    'actual_end',
                    '実績終了は実績開始以降の日付を指定してください。',
                );
            },
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function mergedScheduleValues(): array
    {
        /** @var Issue|null $issue */
        $issue = $this->route('issue');
        $currentSchedule = $issue?->schedule;
        $payload = $this->all();

        return [
            'planned_start' => array_key_exists('planned_start', $payload) ? $payload['planned_start'] : $currentSchedule?->planned_start?->toDateString(),
            'planned_end' => array_key_exists('planned_end', $payload) ? $payload['planned_end'] : $currentSchedule?->planned_end?->toDateString(),
            'actual_start' => array_key_exists('actual_start', $payload) ? $payload['actual_start'] : $currentSchedule?->actual_start?->toDateString(),
            'actual_end' => array_key_exists('actual_end', $payload) ? $payload['actual_end'] : $currentSchedule?->actual_end?->toDateString(),
        ];
    }

    private function validateDateOrder(
        Validator $validator,
        mixed $start,
        mixed $end,
        string $errorKey,
        string $message,
    ): void {
        if (! is_string($start) || ! is_string($end)) {
            return;
        }

        if ($start > $end) {
            $validator->errors()->add($errorKey, $message);
        }
    }
}
