<?php

namespace App\Http\Resources;

use App\Enums\SyncStatus;
use App\Enums\SyncTrigger;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SyncLog
 */
class SyncLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SyncLog $log */
        $log = $this->resource;
        $triggeredBy = $log->relationLoaded('triggeredByUser') ? $log->triggeredByUser : null;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'trigger' => $this->trigger instanceof SyncTrigger ? $this->trigger->value : $this->trigger,
            'github_delivery_id' => $this->github_delivery_id,
            'triggered_by' => $triggeredBy instanceof User
                ? ['id' => $triggeredBy->id, 'name' => $triggeredBy->name]
                : null,
            'attempt_count' => $this->attempt_count,
            'status' => $this->status instanceof SyncStatus ? $this->status->value : $this->status,
            'created_count' => $this->created_count,
            'updated_count' => $this->updated_count,
            'skipped_count' => $this->skipped_count,
            'failed_count' => $this->failed_count,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at->toJSON(),
            'finished_at' => $this->finished_at?->toJSON(),
        ];
    }
}
