<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateSearchEmbeddingsChunkJob;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\Search\SearchDocumentBuilder;
use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateSearchEmbeddingsChunkJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeOpenAi(): void
    {
        Http::fake(['api.openai.com/*' => fn (Request $request) => Http::response([
            'data' => collect($request['input'])
                ->map(fn (string $input, int $i) => ['index' => $i, 'embedding' => TitleSearchEmbeddingFactory::unitVector($i)])
                ->all(),
        ])]);
    }

    public function test_stores_an_embedding_per_title_including_adult_titles(): void
    {
        $this->fakeOpenAi();
        $titles = collect([
            Title::factory()->create(),
            Title::factory()->create(['is_adult' => true]),
        ]);

        GenerateSearchEmbeddingsChunkJob::dispatchSync($titles->pluck('id')->all());

        $this->assertSame(2, TitleSearchEmbedding::where('document_version', SearchDocumentBuilder::VERSION)->count());
        Http::assertSent(fn (Request $request) => $request['dimensions'] === TitleSearchEmbedding::DIMENSIONS
            && count($request['input']) === 2);
    }

    public function test_overwrites_a_stale_embedding_version(): void
    {
        $this->fakeOpenAi();
        $title = Title::factory()->create();
        TitleSearchEmbedding::factory()->for($title)->create(['document_version' => SearchDocumentBuilder::VERSION - 1]);

        GenerateSearchEmbeddingsChunkJob::dispatchSync([$title->id]);

        $this->assertSame(SearchDocumentBuilder::VERSION, $title->searchEmbedding()->value('document_version'));
        $this->assertSame(1, TitleSearchEmbedding::count());
    }

    public function test_does_nothing_for_unknown_titles(): void
    {
        Http::fake();

        GenerateSearchEmbeddingsChunkJob::dispatchSync([999_999]);

        Http::assertNothingSent();
        $this->assertSame(0, TitleSearchEmbedding::count());
    }

    public function test_runs_on_the_embeddings_queue(): void
    {
        $this->assertSame('embeddings', (new GenerateSearchEmbeddingsChunkJob([1]))->queue);
    }
}
