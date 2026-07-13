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
        Schema::table('titles', static function (Blueprint $table) {
            // Nullable, not default(null): on Postgres timestamp() is NOT NULL
            // unless declared otherwise, and the backfill query selects on
            // credits_filled_at IS NULL.
            $table->timestamp('credits_filled_at')->after('enriched_at')->nullable();

            // FetchTitlesCreditsJob sorts the whole table by popularity to pick
            // its next slice; without this it's a seq scan + sort every run.
            $table->index(['popularity', 'credits_filled_at'], 'titles_credits_backfill_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('titles', static function (Blueprint $table) {
            $table->dropIndex('titles_credits_backfill_index');
            $table->dropColumn('credits_filled_at');
        });
    }
};
