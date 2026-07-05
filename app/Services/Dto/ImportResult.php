<?php

namespace App\Services\Dto;

class ImportResult
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $failed = 0;

    public ?string $firstError = null;

    public function merge(self $other): void
    {
        $this->created += $other->created;
        $this->updated += $other->updated;
        $this->skipped += $other->skipped;
        $this->failed += $other->failed;
        $this->firstError ??= $other->firstError;
    }
}
