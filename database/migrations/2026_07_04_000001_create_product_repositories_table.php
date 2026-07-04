<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('owner', 100);
            $table->string('repo', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->enum('last_sync_status', ['success', 'partial'])->nullable();
            $table->timestamps();

            $table->unique(['owner', 'repo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_repositories');
    }
};
