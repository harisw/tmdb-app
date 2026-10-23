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
}
