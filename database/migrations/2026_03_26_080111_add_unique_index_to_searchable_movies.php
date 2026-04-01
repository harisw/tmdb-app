<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }
        DB::statement('CREATE UNIQUE INDEX searchable_movies_id_idx ON searchable_movies (movie_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS searchable_movies_id_idx');
    }
};
