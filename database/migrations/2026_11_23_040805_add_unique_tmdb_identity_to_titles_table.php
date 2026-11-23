<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_REFERENCES = [
        ['genre_title', 'title_id', ['genre_id']],
        ['keyword_title', 'title_id', ['keyword_id']],
        ['title_production_company', 'title_id', ['production_company_id']],
        ['title_providers', 'title_id', ['country_provider_id', 'type']],
        ['title_person', 'title_id', ['person_id', 'job']],
        ['title_similarities', 'title_id', ['similar_title_id']],
        ['title_similarities', 'similar_title_id', ['title_id']],
        ['title_search_embeddings', 'title_id', []],
        ['user_title_states', 'title_id', ['user_id']],
    ];

    public function up(): void
    {
        $this->mergeDuplicateTitles();

        DB::table('titles')
            ->where('slug', '')
            ->whereNotNull('tmdb_id')
            ->update(['slug' => DB::raw("type || '-' || tmdb_id")]);

        Schema::table('titles', function (Blueprint $table) {
            $table->dropIndex(['tmdb_id']);
            $table->unique(['tmdb_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('titles', function (Blueprint $table) {
            $table->dropUnique(['tmdb_id', 'type']);
            $table->index('tmdb_id');
        });
    }

    private function mergeDuplicateTitles(): void
    {
        $duplicates = DB::table('titles')
            ->whereNotNull('tmdb_id')
            ->select('tmdb_id', 'type', DB::raw('array_agg(id ORDER BY id DESC) AS ids'))
            ->groupBy('tmdb_id', 'type')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            $ids = array_map('intval', explode(',', trim($group->ids, '{}')));
            $keeperId = array_shift($ids);

            foreach ($ids as $duplicateId) {
                $this->moveReferences($duplicateId, $keeperId);
            }
        }
    }

    private function moveReferences(int $duplicateId, int $keeperId): void
    {
        foreach (self::UNIQUE_REFERENCES as [$table, $column, $uniqueWith]) {
            $sameKey = implode('', array_map(fn (string $key): string => " AND k.{$key} = {$table}.{$key}", $uniqueWith));

            DB::update(
                "UPDATE {$table} SET {$column} = ? WHERE {$column} = ? AND NOT EXISTS (SELECT 1 FROM {$table} k WHERE k.{$column} = ?{$sameKey})",
                [$keeperId, $duplicateId, $keeperId],
            );
        }

        DB::table('title_interaction_events')->where('title_id', $duplicateId)->update(['title_id' => $keeperId]);
        DB::table('title_similarities')->whereColumn('title_id', 'similar_title_id')->delete();
        DB::table('user_title_states')->where('title_id', $duplicateId)->delete();
        DB::table('titles')->where('id', $duplicateId)->delete();
    }
};
