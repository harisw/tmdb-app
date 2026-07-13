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
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('gender');
            $table->boolean('is_adult')->default(false);
            $table->string('profile_path')->nullable();
            $table->float('popularity')->default(0);
            $table->unsignedBigInteger('tmdb_id')->nullable()->unique();
            $table->schemalessAttributes('extra_attributes')->nullable();
            $table->timestamps();
        });

        Schema::create('title_person', function (Blueprint $table) {
            $table->id();
            $table->foreignId('title_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('job');
            // text, not string: aggregate_credits joins every role a person
            // played, which overruns varchar(255) on long-running series.
            $table->text('character')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->unsignedInteger('episode_count')->nullable()->after('order');
            $table->timestamps();

            $table->unique(['title_id', 'person_id', 'job'], 'title_person_unique_title_person_job');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('title_person');
        Schema::dropIfExists('people');
    }
};
