<?php

namespace App\Services\KnowledgeGraph;

use Illuminate\Support\Facades\Http;
use Log;

class OpenAIEmbeddingService
{
    private const MODEL = 'text-embedding-3-small';

    private const MAX_BATCH = 100;

    public function embedOne(string $text, ?int $dimensions = null): array
    {
        return $this->embedBatch([$text], $dimensions)[0];
    }

    public function embedBatch(array $texts, ?int $dimensions = null): array
    {
        $response = Http::keepAlive()
            ->withToken(config('services.openai.key'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/embeddings', array_filter([
                'model' => self::MODEL,
                'input' => $texts,
                'encoding_format' => 'float',
                'dimensions' => $dimensions,
            ]));

        if ($response->failed()) {
            Log::error('OpenAI embedding failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenAI embedding request failed: '.$response->status());
        }

        // Sort by index to guarantee order matches input
        $data = collect($response->json('data'))
            ->sortBy('index')
            ->values();

        return $data->map(fn ($item) => $item['embedding'])->all();
    }

    public function embedBatchChunked(array $texts, int $chunkSize = self::MAX_BATCH): array
    {
        $results = [];
        foreach (array_chunk($texts, $chunkSize) as $chunk) {
            $results[] = $this->embedBatch($chunk);
        }

        return array_merge(...$results);
    }
}
