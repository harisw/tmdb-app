<?php

namespace Tests\Feature\Console;

use App\Models\Genre;
use App\Models\ProductionCompany;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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

    public function test_scores_the_hyde_mode_with_the_configured_fusion_method(): void
    {
        config(['services.llm.providers' => ['openai'], 'search.fusion' => 'z-score']);
        Http::fake(fn (Request $request) => str_ends_with($request->url(), '/chat/completions')
            ? Http::response(['choices' => [['message' => ['content' => json_encode(['title' => 'The Martian', 'document' => 'The Martian'])]]]])
            : Http::response(['data' => [['index' => 0, 'embedding' => TitleSearchEmbeddingFactory::unitVector(3)]]]));
        $martian = TitleSearchEmbedding::factory()->pointingAt(3)->create();
        $this->writeGoldenSet([
            ['query' => 'astronaut stranded on mars', 'category' => 'descriptive', 'relevant' => [$martian->title_id]],
        ]);

        $this->artisan('search:evaluate', ['mode' => 'hyde', '--golden-set' => $this->goldenSetPath])
            ->expectsOutputToContain('Mode: hyde, fusion: z-score')
            ->expectsTable(['Category', 'Queries', 'Recall@10', 'MRR'], [
                ['descriptive', 1, '1.000', '1.000'],
                ['overall', 1, '1.000', '1.000'],
            ])
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions'));
    }

    public function test_rejects_an_unknown_mode(): void
    {
        $this->artisan('search:evaluate', ['mode' => 'magic'])
            ->expectsOutputToContain('Mode must be one of')
            ->assertFailed();
    }
}
