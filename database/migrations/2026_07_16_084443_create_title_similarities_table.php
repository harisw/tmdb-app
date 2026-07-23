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
        Schema::create('title_similarities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->foreignId('similar_title_id')->constrained('titles')->cascadeOnDelete();
            $table->float('score');
            $table->jsonb('reasons')->default('[]');
            $table->timestamps();

            $table->unique(['title_id', 'similar_title_id']);
            $table->index(['title_id', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('title_similarities');
    }
};
