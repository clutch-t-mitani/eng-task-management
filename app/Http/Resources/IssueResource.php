<?php

namespace App\Http\Resources;

use App\Models\Engineer;
use App\Models\Issue;
use App\Models\IssueSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Issue
 */
class IssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $schedule = $this->whenLoaded('schedule');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'github_url' => $this->github_url,
            'github_issue_number' => $this->github_issue_number,
            'github_state' => $this->github_state,
            'github_synced_at' => $this->github_synced_at?->toJSON(),
            'status' => $this->status,
            'is_managed' => $this->is_managed,
            'display_order' => $this->display_order,
            'product_id' => $this->product_id,
            'director' => $this->person($this->whenLoaded('director')),
            'engineer' => $this->person($this->whenLoaded('engineer')),
            'schedule' => $schedule instanceof IssueSchedule ? $this->schedulePayload($schedule) : null,
            'is_overdue' => $this->isOverdue($schedule instanceof IssueSchedule ? $schedule : null),
            'is_due_soon' => $this->isDueSoon($schedule instanceof IssueSchedule ? $schedule : null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function person(mixed $person): ?array
    {
        if (! $person instanceof User && ! $person instanceof Engineer) {
            return null;
        }

        return [
            'id' => $person->id,
            'name' => $person->trashed() ? '削除済みユーザー' : $person->name,
            'deleted' => $person->trashed(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulePayload(IssueSchedule $schedule): array
    {
        return [
            'planned_start' => $schedule->planned_start?->toDateString(),
            'planned_end' => $schedule->planned_end?->toDateString(),
            'actual_start' => $schedule->actual_start?->toDateString(),
            'actual_end' => $schedule->actual_end?->toDateString(),
        ];
    }

    private function isOverdue(?IssueSchedule $schedule): bool
    {
        if (! $schedule?->planned_end || $schedule->actual_end || $this->status === '完了') {
            return false;
        }

        return $schedule->planned_end->lt(Carbon::today());
    }

    private function isDueSoon(?IssueSchedule $schedule): bool
    {
        if (! $schedule?->planned_end || $schedule->actual_end || $this->status === '完了') {
            return false;
        }

        return $schedule->planned_end->betweenIncluded(Carbon::today(), Carbon::today()->addDays(3));
    }
}
