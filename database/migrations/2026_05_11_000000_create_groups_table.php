<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->date('release_date')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index('product_id');
            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
