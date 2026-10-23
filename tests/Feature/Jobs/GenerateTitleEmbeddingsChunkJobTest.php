<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateTitleEmbeddingsChunkJob;
use App\Models\Keyword;
use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerateTitleEmbeddingsChunkJobTest extends TestCase
{
    use RefreshDatabase;

    private const DIMENSIONS = 1536;

    private function fakeOpenAi(): void
    {
        Http::fake(['api.openai.com/*' => fn (Request $request) => Http::response([
            'data' => collect($request['input'])
                ->map(fn (string $input, int $i) => ['index' => $i, 'embedding' => array_fill(0, self::DIMENSIONS, 0.1)])
                ->all(),
        ])]);
    }

    public function test_leaves_keywords_excluded_from_similarity_out_of_the_document(): void
    {
        $this->fakeOpenAi();
        $title = Title::factory()->create();
        $title->keywords()->attach([
            Keyword::factory()->create(['name' => 'based on novel', 'use_in_similarity' => false])->id,
            Keyword::factory()->create(['name' => 'heist'])->id,
        ]);

        GenerateTitleEmbeddingsChunkJob::dispatchSync([$title->id]);

        Http::assertSent(fn (Request $request) => str_contains($request['input'][0], 'heist')
            && ! str_contains($request['input'][0], 'based on novel'));
        $this->assertNotNull(Title::withoutGlobalScopes()->find($title->id)->embedding);
    }
}
