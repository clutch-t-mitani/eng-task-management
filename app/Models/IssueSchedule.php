<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueSchedule extends Model
{
    protected $fillable = [
        'issue_id',
        'planned_start',
        'planned_end',
        'actual_start',
        'actual_end',
    ];

    protected function casts(): array
    {
        return [
            'planned_start' => 'date:Y-m-d',
            'planned_end' => 'date:Y-m-d',
            'actual_start' => 'date:Y-m-d',
            'actual_end' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
