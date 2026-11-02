<?php

namespace Tests\Feature\Seeders;

use App\Models\Country;
use App\Models\Language;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CountrySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_drops_a_countries_cache_built_from_an_empty_table(): void
    {
        Language::factory()->create(['code' => 'other']);
        Cache::forever('countries', []);

        $this->seed(CountrySeeder::class);

        $this->assertFalse(Cache::has('countries'));
        $this->assertGreaterThan(0, Country::count());
    }

    public function test_seeding_maps_iso3_and_language_from_the_bundled_data(): void
    {
        $other = Language::factory()->create(['code' => 'other']);
        $catalan = Language::factory()->create(['code' => 'ca']);

        $this->seed(CountrySeeder::class);

        $this->assertDatabaseHas('countries', ['iso2' => 'AD', 'iso3' => 'AND', 'name' => 'Andorra', 'language_id' => $catalan->id]);
        $this->assertDatabaseHas('countries', ['iso2' => 'AE', 'iso3' => 'ARE', 'language_id' => $other->id]);
        $this->assertDatabaseHas('countries', ['iso2' => 'MY', 'iso3' => 'MYS', 'language_id' => $other->id]);
        $this->assertDatabaseHas('countries', ['iso2' => 'YU', 'iso3' => null, 'language_id' => $other->id]);
    }
}
