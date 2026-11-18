<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateSearchEmbeddingsChunkJob;
use App\Jobs\Middleware\MeasureDatabaseCost;
use App\Models\Title;
use App\Models\TitleSearchEmbedding;
use App\Services\Search\SearchDocumentBuilder;
use Database\Factories\TitleSearchEmbeddingFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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

    public function test_stores_an_embedding_per_title(): void
    {
        $this->fakeOpenAi();
        $titles = collect([
            Title::factory()->create(),
            Title::factory()->create(),
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

    public function test_upserts_under_a_statement_timeout_shorter_than_the_job_timeout(): void
    {
        $this->fakeOpenAi();
        $title = Title::factory()->create();
        $timeoutSecondsDuringUpsert = null;

        DB::listen(function (QueryExecuted $query) use (&$timeoutSecondsDuringUpsert): void {
            if (str_starts_with($query->sql, 'insert into "title_search_embeddings"')) {
                $timeoutSecondsDuringUpsert = (int) DB::scalar("select extract(epoch from current_setting('statement_timeout')::interval)");
            }
        });

        GenerateSearchEmbeddingsChunkJob::dispatchSync([$title->id]);

        $this->assertGreaterThan(0, $timeoutSecondsDuringUpsert);
        $this->assertLessThan((new GenerateSearchEmbeddingsChunkJob([1]))->timeout, $timeoutSecondsDuringUpsert);
    }

    public function test_fails_without_upserting_when_the_job_has_no_time_left(): void
    {
        Http::fake(['api.openai.com/*' => function (Request $request) {
            $this->travel((new GenerateSearchEmbeddingsChunkJob([1]))->timeout)->seconds();

            return Http::response(['data' => [['index' => 0, 'embedding' => TitleSearchEmbeddingFactory::unitVector(0)]]]);
        }]);
        $failures = [];
        Event::listen(JobFailed::class, function (JobFailed $event) use (&$failures): void {
            $failures[] = $event->exception;
        });
        $costs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$costs): void {
            if ($event->message === 'Job database cost') {
                $costs[] = $event->context;
            }
        });
        $title = Title::factory()->create();

        GenerateSearchEmbeddingsChunkJob::dispatchSync([$title->id]);

        $this->assertCount(1, $failures);
        $this->assertSame(0, TitleSearchEmbedding::count());
        $this->assertSame(['failed'], array_column($costs, 'outcome'));
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

    public function test_measures_its_database_cost_on_search_embeddings(): void
    {
        $middleware = (new GenerateSearchEmbeddingsChunkJob([1]))->middleware();

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(MeasureDatabaseCost::class, $middleware[0]);
        $this->assertSame(['title_search_embeddings'], $middleware[0]->tables);
    }
}
