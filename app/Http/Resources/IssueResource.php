<?php

namespace App\Http\Resources;

use App\Enums\GitHubIssueState;
use App\Enums\IssueStatus;
use App\Models\Engineer;
use App\Models\GroupIssue;
use App\Models\Issue;
use App\Models\IssueSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        /** @var Issue $issue */
        $issue = $this->resource;
        $schedule = $this->whenLoaded('schedule');
        $loadedSchedule = $schedule instanceof IssueSchedule ? $schedule : null;
        $groupIssue = $this->whenLoaded('groupIssue');
        $loadedGroupIssue = $groupIssue instanceof GroupIssue ? $groupIssue : null;
        $group = $loadedGroupIssue?->relationLoaded('group') ? $loadedGroupIssue->group : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'github_url' => $this->github_url,
            'github_issue_number' => $this->github_issue_number,
            'github_state' => $this->github_state instanceof GitHubIssueState ? $this->github_state->value : $this->github_state,
            'github_synced_at' => $this->github_synced_at?->toJSON(),
            'status_id' => $this->status_id,
            'status_label' => IssueStatus::tryFrom((int) $this->status_id)?->label(),
            'is_managed' => $this->is_managed,
            'display_order' => $this->display_order,
            'product_id' => $this->product_id,
            'group_id' => $group?->id,
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'release_date' => $group->release_date?->toDateString(),
                'display_order' => $group->display_order,
                'product_id' => $group->product_id,
            ] : null,
            'director' => $this->person($this->whenLoaded('director')),
            'engineer' => $this->person($this->whenLoaded('engineer')),
            'schedule' => $loadedSchedule ? $this->schedulePayload($loadedSchedule) : null,
            'is_overdue' => $loadedSchedule ? $issue->isOverdue($loadedSchedule) : false,
            'is_due_soon' => $loadedSchedule ? $issue->isDueSoon($loadedSchedule) : false,
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
}
