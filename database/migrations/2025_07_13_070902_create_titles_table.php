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
        Schema::create('titles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type');
            $table->float('rating', 2);
            $table->string('status');
            $table->date('release_date');
            $table->bigInteger('revenue');
            $table->unsignedSmallInteger('runtime');
            $table->string('backdrop_path')->nullable();
            $table->string('imdb_id')->nullable();
            $table->text('overview')->nullable();
            $table->float('popularity')->default(0);
            $table->string('poster_path')->nullable();
            $table->text('tagline')->nullable();
            $table->unsignedBigInteger('tmdb_id')->nullable()->index();
            $table->string('homepage')->nullable();
            $table->foreignId('language_id')->constrained();
            $table->foreignId('collection_id')->nullable()->constrained('title_collections');
            $table->index('language_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('titles');
    }
};
