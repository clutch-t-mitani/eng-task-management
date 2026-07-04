<?php

namespace App\Http\Resources;

use App\Enums\SyncStatus;
use App\Models\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductRepository
 */
class ProductRepositoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'owner' => $this->owner,
            'repo' => $this->repo,
            'is_active' => $this->is_active,
            'last_synced_at' => $this->last_synced_at?->toJSON(),
            'last_sync_status' => $this->last_sync_status instanceof SyncStatus ? $this->last_sync_status->value : $this->last_sync_status,
        ];
    }
}
