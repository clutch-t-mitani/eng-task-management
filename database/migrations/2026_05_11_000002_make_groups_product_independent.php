<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('groups', 'product_id')) {
            return;
        }

        $groupIssues = Schema::hasTable('group_issues')
            ? DB::table('group_issues')->get()->map(fn (object $row): array => (array) $row)->all()
            : [];

        DB::table('groups')
            ->orderBy('product_id')
            ->orderBy('display_order')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $groupId, int $index): void {
                DB::table('groups')
                    ->where('id', $groupId)
                    ->update(['display_order' => $index + 1]);
            });

        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('groups', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
            });

            Schema::table('groups', function (Blueprint $table) {
                $table->dropIndex(['product_id']);
            });

            Schema::table('groups', function (Blueprint $table) {
                $table->dropColumn('product_id');
            });
        });

        foreach (array_chunk($groupIssues, 500) as $rows) {
            DB::table('group_issues')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('groups', 'product_id')) {
            return;
        }

        $unrestorableGroupIds = DB::table('groups')
            ->leftJoin('group_issues', 'group_issues.group_id', '=', 'groups.id')
            ->leftJoin('issues', 'issues.id', '=', 'group_issues.issue_id')
            ->groupBy('groups.id')
            ->havingRaw('COUNT(DISTINCT issues.product_id) != 1')
            ->pluck('groups.id');

        if ($unrestorableGroupIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot roll back product-independent groups because every group must contain ISSUEs from exactly one product. '
                .'Unrestorable group IDs: '.$unrestorableGroupIds->implode(', ')
            );
        }

        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable();
            $table->index('product_id');
        });

        DB::table('groups')
            ->pluck('id')
            ->each(function (int $groupId): void {
                $productIds = DB::table('group_issues')
                    ->join('issues', 'issues.id', '=', 'group_issues.issue_id')
                    ->where('group_issues.group_id', $groupId)
                    ->distinct()
                    ->pluck('issues.product_id');

                DB::table('groups')
                    ->where('id', $groupId)
                    ->update(['product_id' => $productIds->first()]);
            });

        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
