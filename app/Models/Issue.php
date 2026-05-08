<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Issue extends Model
{
    public const STATUSES = ['未着手', '作業中', 'テスト中', '完了', '保留'];

    protected $fillable = [
        'title',
        'github_url',
        'director_id',
        'engineer_id',
        'product_id',
        'status',
        'is_managed',
        'display_order',
        'github_issue_number',
        'github_state',
        'github_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_managed' => 'boolean',
            'github_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function director(): BelongsTo
    {
        return $this->belongsTo(User::class, 'director_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Engineer, $this>
     */
    public function engineer(): BelongsTo
    {
        return $this->belongsTo(Engineer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasOne<IssueSchedule, $this>
     */
    public function schedule(): HasOne
    {
        return $this->hasOne(IssueSchedule::class);
    }

    /**
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    public function scopeManaged(Builder $query): Builder
    {
        return $query->where('is_managed', true);
    }

    /**
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    public function scopeUnmanagedImports(Builder $query): Builder
    {
        return $query
            ->where('is_managed', false)
            ->whereNotNull('github_issue_number');
    }

    /**
     * @param  Builder<Issue>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Issue>
     */
    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['product_id'] ?? null, fn (Builder $query, mixed $value): Builder => $this->applyFilterValue($query, 'product_id', $value))
            ->when($filters['engineer_id'] ?? null, fn (Builder $query, mixed $value): Builder => $this->applyFilterValue($query, 'engineer_id', $value))
            ->when($filters['director_id'] ?? null, fn (Builder $query, mixed $value): Builder => $this->applyFilterValue($query, 'director_id', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $value): Builder => $this->applyFilterValue($query, 'status', $value));
    }

    /**
     * @param  Builder<Issue>  $query
     * @return Builder<Issue>
     */
    private function applyFilterValue(Builder $query, string $column, mixed $value): Builder
    {
        if (is_array($value)) {
            return $query->whereIn($column, $value);
        }

        return $query->where($column, $value);
    }
}
