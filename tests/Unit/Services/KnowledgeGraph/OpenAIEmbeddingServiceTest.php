<?php

namespace Tests\Unit\Services\KnowledgeGraph;

use App\Services\KnowledgeGraph\OpenAIEmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIEmbeddingServiceTest extends TestCase
{
    public function test_realigns_embeddings_to_input_order_by_index(): void
    {
        // OpenAI may return items out of order; the service must re-sort by
        // `index` so $vectors[$i] lines up with the i-th input title. A bug here
        // silently writes the wrong embedding to a title.
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['index' => 1, 'embedding' => [0.4, 0.5, 0.6]],
                    ['index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
                ],
            ]),
        ]);

        $result = app(OpenAIEmbeddingService::class)->embedBatch(['first', 'second']);

        $this->assertSame([[0.1, 0.2, 0.3], [0.4, 0.5, 0.6]], $result);
    }

    public function test_sends_the_expected_model_and_payload(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1]]]]),
        ]);

        app(OpenAIEmbeddingService::class)->embedBatch(['hello']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request['model'] === 'text-embedding-3-small'
            && $request['encoding_format'] === 'float'
            && $request['input'] === ['hello']);
    }

    public function test_sends_dimensions_only_when_requested(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1]]]]),
        ]);

        app(OpenAIEmbeddingService::class)->embedOne('hello', 512);
        app(OpenAIEmbeddingService::class)->embedOne('hello');

        Http::assertSent(fn ($request) => ($request->data()['dimensions'] ?? null) === 512);
        Http::assertSent(fn ($request) => ! isset($request['dimensions']));
    }

    public function test_throws_when_openai_responds_with_an_error(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('rate limited', 429)]);

        $this->expectException(\RuntimeException::class);

        app(OpenAIEmbeddingService::class)->embedBatch(['boom']);
    }
}
