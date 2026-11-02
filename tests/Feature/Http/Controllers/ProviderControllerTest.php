<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Provider;
use App\Models\Title;
use App\Models\TitleProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProviderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Bus::fake();
    }

    private function listOn(Provider $provider, Title $title): void
    {
        TitleProvider::factory()->create(['provider_id' => $provider->id, 'title_id' => $title->id]);
    }

    private function providerWithTitles(int $count, array $provider = [], array $title = []): Provider
    {
        $model = Provider::factory()->create($provider);
        $model->forceFill(['count' => $count])->save();
        Title::factory($count)->create($title)->each(fn (Title $listed) => $this->listOn($model, $listed));

        return $model;
    }

    public function test_index_lists_providers_by_stored_count_skipping_empty_ones(): void
    {
        $small = $this->providerWithTitles(2);
        $large = $this->providerWithTitles(4);
        Provider::factory()->create();

        $this->get('/provider/all')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('AllProviders')
                ->has('allProviders', 2)
                ->where('allProviders.0.id', $large->id)
                ->where('allProviders.0.count', 4)
                ->where('allProviders.1.id', $small->id)
                ->where('allProviders.1.count', 2)
                ->missing('allProviders.0.titles')
            );
    }

    public function test_index_featured_row_lists_a_title_from_several_countries_once(): void
    {
        $provider = Provider::factory()->create();
        $provider->forceFill(['count' => 1])->save();
        $title = Title::factory()->create();
        $this->listOn($provider, $title);
        $this->listOn($provider, $title);

        $this->get('/provider/all')->assertInertia(fn ($page) => $page->has('featuredProviders.0.titles', 1));
    }

    public function test_index_drops_featured_rows_that_come_back_empty(): void
    {
        $listed = $this->providerWithTitles(1);
        Provider::factory()->create()->forceFill(['count' => 500])->save();

        $this->get('/provider/all')->assertInertia(fn ($page) => $page
            ->has('featuredProviders', 1)
            ->where('featuredProviders.0.id', $listed->id)
        );
    }

    public function test_index_features_only_the_biggest_providers(): void
    {
        foreach (range(1, 8) as $size) {
            $this->providerWithTitles($size);
        }

        $this->get('/provider/all')->assertInertia(fn ($page) => $page
            ->has('allProviders', 8)
            ->has('featuredProviders', 6)
            ->where('featuredProviders.0.count', 8)
            ->where('featuredProviders.5.count', 3)
        );
    }

    public function test_index_featured_rows_are_capped_and_most_popular_first(): void
    {
        $provider = $this->providerWithTitles(20, title: ['popularity' => 10]);
        $loud = Title::factory()->create(['popularity' => 99]);
        $this->listOn($provider, $loud);

        $this->get('/provider/all')->assertInertia(fn ($page) => $page
            ->has('featuredProviders.0.titles', 15)
            ->where('featuredProviders.0.titles.0.id', $loud->id)
        );
    }

    public function test_show_returns_each_title_once(): void
    {
        $provider = Provider::factory()->create(['slug' => 'netflix']);
        $title = Title::factory()->create();
        $this->listOn($provider, $title);
        $this->listOn($provider, $title);
        $this->providerWithTitles(1);

        $this->get('/provider/netflix')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('TitleByProvider')
                ->where('provider.id', $provider->id)
                ->has('titles.data', 1)
                ->where('titles.meta.total', 1)
            );
    }

    public function test_show_sorts_by_popularity_by_default_and_accepts_newest(): void
    {
        $provider = Provider::factory()->create(['slug' => 'netflix']);
        $oldHit = Title::factory()->create(['popularity' => 99, 'release_date' => '1999-01-01']);
        $newNiche = Title::factory()->create(['popularity' => 1, 'release_date' => '2024-01-01']);
        $this->listOn($provider, $oldHit);
        $this->listOn($provider, $newNiche);

        $this->get('/provider/netflix')->assertInertia(fn ($page) => $page
            ->where('sort', 'popular')
            ->where('titles.data.0.id', $oldHit->id)
        );

        $this->get('/provider/netflix?sort=newest')->assertInertia(fn ($page) => $page
            ->where('sort', 'newest')
            ->where('titles.data.0.id', $newNiche->id)
        );
    }

    public function test_show_rejects_an_unknown_sort(): void
    {
        Provider::factory()->create(['slug' => 'netflix']);

        $this->get('/provider/netflix?sort=bogus')->assertSessionHasErrors('sort');
    }
}
