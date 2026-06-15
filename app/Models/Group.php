<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = [
        'name',
        'release_date',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'display_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<GroupIssue, $this>
     */
    public function groupIssues(): HasMany
    {
        return $this->hasMany(GroupIssue::class);
    }
}
