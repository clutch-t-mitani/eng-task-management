<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('issue_schedules', function (Blueprint $table) {
            $table->dropColumn(['planned_hours', 'actual_hours']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issue_schedules', function (Blueprint $table) {
            $table->decimal('planned_hours', 6, 2)->nullable()->after('actual_end');
            $table->decimal('actual_hours', 6, 2)->nullable()->after('planned_hours');
        });
    }
};
