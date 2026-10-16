<?php

namespace Tests\Feature\Console;

use App\Models\Genre;
use App\Models\ProductionCompany;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SearchEvaluateTest extends TestCase
{
    use RefreshDatabase;

    private string $goldenSetPath = 'storage/framework/testing/search-golden-set.json';

    protected function tearDown(): void
    {
        File::delete(base_path($this->goldenSetPath));

        parent::tearDown();
    }

    private function writeGoldenSet(array $cases): void
    {
        File::ensureDirectoryExists(dirname(base_path($this->goldenSetPath)));
        File::put(base_path($this->goldenSetPath), json_encode($cases));
    }

    private function createSearchableTitle(string $name): Title
    {
        $title = Title::factory()->create(['name' => $name]);
        $title->genres()->attach(Genre::factory()->create());
        $title->companies()->attach(ProductionCompany::factory()->create());

        return $title;
    }

    public function test_reports_recall_and_mrr_per_category_and_lists_misses(): void
    {
        $galaxy = $this->createSearchableTitle('Galaxy Quest');
        $sequel = $this->createSearchableTitle('Galaxy Quest Returns');
        Artisan::call('titles:refresh-searchable');
        $this->writeGoldenSet([
            ['query' => 'galaxy quest', 'category' => 'exact', 'relevant' => [$galaxy->id, $sequel->id]],
            ['query' => 'astronaut stranded on mars', 'category' => 'descriptive', 'relevant' => [$galaxy->id]],
        ]);

        $this->artisan('search:evaluate', ['mode' => 'fts', '--golden-set' => $this->goldenSetPath])
            ->expectsTable(['Category', 'Queries', 'Recall@10', 'MRR'], [
                ['exact', 1, '1.000', '1.000'],
                ['descriptive', 1, '0.000', '0.000'],
                ['overall', 2, '0.500', '0.500'],
            ])
            ->expectsOutputToContain('[descriptive] astronaut stranded on mars')
            ->assertSuccessful();
    }

    public function test_rejects_an_unknown_mode(): void
    {
        $this->artisan('search:evaluate', ['mode' => 'magic'])
            ->expectsOutputToContain('Mode must be one of')
            ->assertFailed();
    }
}
