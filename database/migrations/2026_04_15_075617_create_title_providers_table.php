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
        Schema::create('title_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_provider')->constrained();
            // FOR redundancies
            $table->foreignId('country_id')->constrained();
            $table->foreignId('provider_id')->constrained();
            $table->foreignId('title_id')->constrained();
            $table->string('type');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->json('audio_languages');
            $table->json('subtitle_languages');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('title_providers');
    }
};
