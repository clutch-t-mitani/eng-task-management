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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('title', 500);
            $table->string('github_url', 500);
            $table->foreignId('director_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('engineer_id')->nullable()->constrained('engineers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->boolean('is_managed')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('github_issue_number');
            $table->enum('github_state', ['open', 'closed']);
            $table->timestamp('github_synced_at');
            $table->timestamps();

            $table->index('director_id');
            $table->index('engineer_id');
            $table->index('product_id');
            $table->index('status_id');
            $table->index('is_managed');
            $table->index('github_state');
            $table->index(['product_id', 'display_order']);
            $table->unique(['product_id', 'github_issue_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
