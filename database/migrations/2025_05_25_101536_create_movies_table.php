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
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->float('rating', 2);
            $table->string('status', 100);
            $table->date('release_date');
            $table->bigInteger('revenue');
            $table->unsignedSmallInteger('runtime');
            $table->string('img_backdrop')->nullable();
            $table->string('imdb_id')->nullable();
            $table->text('overview')->nullable();
            $table->float('popularity')->default(0);
            $table->string('img_poster')->nullable();
            $table->text('tagline')->nullable();

            $table->foreignIdFor(\App\Models\Language::class);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
