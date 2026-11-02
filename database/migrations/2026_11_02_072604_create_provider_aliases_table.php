<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_aliases', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
        });

        DB::statement("
            INSERT INTO provider_aliases (provider_id, source, external_id, name, created_at, updated_at)
            SELECT id, 'tmdb', tmdb_id::text, name, now(), now() FROM providers
        ");

        Schema::table('providers', static function (Blueprint $table) {
            $table->dropConstrainedForeignId('main_provider_id');
            $table->dropColumn('tmdb_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', static function (Blueprint $table) {
            $table->unsignedInteger('tmdb_id')->nullable()->unique();
            $table->foreignId('main_provider_id')->nullable()->references('id')->on('providers');
        });

        DB::statement("
            UPDATE providers SET tmdb_id = aliases.tmdb_id
            FROM (
                SELECT provider_id, MIN(external_id::int) AS tmdb_id
                FROM provider_aliases WHERE source = 'tmdb'
                GROUP BY provider_id
            ) AS aliases
            WHERE aliases.provider_id = providers.id
        ");

        Schema::dropIfExists('provider_aliases');
    }
};
