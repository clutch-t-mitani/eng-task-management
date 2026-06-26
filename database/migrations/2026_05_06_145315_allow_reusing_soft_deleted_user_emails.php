<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists('users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_email_unique');
            });
        }

        if (! Schema::hasColumn('users', 'active_email')) {
            DB::statement('
                ALTER TABLE users
                ADD active_email VARCHAR(255)
                GENERATED ALWAYS AS (
                    CASE WHEN deleted_at IS NULL THEN email ELSE NULL END
                ) STORED
            ');
        }

        if (! $this->indexExists('users_active_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('active_email', 'users_active_email_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists('users_active_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_active_email_unique');
            });
        }

        if (Schema::hasColumn('users', 'active_email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('active_email');
            });
        }

        if (! $this->indexExists('users_email_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'users')
            ->where('index_name', $indexName)
            ->exists();
    }
};
