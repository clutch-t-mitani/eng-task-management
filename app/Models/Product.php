<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'name',
        'description',
        'display_order',
    ];

    /**
     * @return HasMany<Issue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * @return HasOne<ProductRepository, $this>
     */
    public function repository(): HasOne
    {
        return $this->hasOne(ProductRepository::class);
    }

    /**
     * @return HasMany<SyncLog, $this>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }
}
