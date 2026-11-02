<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateProviderAliasesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_02_072604_create_provider_aliases_table.php');
    }

    public function test_moves_each_provider_tmdb_id_into_a_tmdb_alias_and_drops_the_old_columns(): void
    {
        $this->migration()->down();
        $netflixId = DB::table('providers')->insertGetId(['tmdb_id' => 8, 'slug' => 'netflix', 'name' => 'Netflix']);
        $variantId = DB::table('providers')->insertGetId([
            'tmdb_id' => 2100,
            'slug' => 'amazon-prime-video-with-ads',
            'name' => 'Amazon Prime Video with Ads',
            'main_provider_id' => $netflixId,
        ]);

        $this->migration()->up();

        $this->assertEqualsCanonicalizing([
            ['provider_id' => $netflixId, 'source' => 'tmdb', 'external_id' => '8', 'name' => 'Netflix'],
            ['provider_id' => $variantId, 'source' => 'tmdb', 'external_id' => '2100', 'name' => 'Amazon Prime Video with Ads'],
        ], DB::table('provider_aliases')->get(['provider_id', 'source', 'external_id', 'name'])->map(fn ($row) => (array) $row)->all());
        $this->assertFalse(Schema::hasColumn('providers', 'tmdb_id'));
        $this->assertFalse(Schema::hasColumn('providers', 'main_provider_id'));
    }

    public function test_rolling_back_restores_tmdb_id_from_the_tmdb_alias(): void
    {
        $providerId = DB::table('providers')->insertGetId(['slug' => 'netflix', 'name' => 'Netflix']);
        DB::table('provider_aliases')->insert([
            ['provider_id' => $providerId, 'source' => 'tmdb', 'external_id' => '8', 'name' => 'Netflix'],
            ['provider_id' => $providerId, 'source' => 'watchmode', 'external_id' => 'Netflix', 'name' => 'Netflix'],
        ]);

        $this->migration()->down();

        $this->assertSame(8, DB::table('providers')->where('id', $providerId)->value('tmdb_id'));
        $this->assertFalse(Schema::hasTable('provider_aliases'));
    }

    public function test_aliases_are_unique_per_source_and_external_id(): void
    {
        $providerId = DB::table('providers')->insertGetId(['slug' => 'netflix', 'name' => 'Netflix']);
        $alias = ['provider_id' => $providerId, 'source' => 'tmdb', 'external_id' => '8', 'name' => 'Netflix'];
        DB::table('provider_aliases')->insert($alias);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('provider_aliases')->insert($alias);
    }
}
