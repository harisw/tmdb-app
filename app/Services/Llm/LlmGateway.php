<?php

namespace App\Services\Llm;

use App\Events\LlmCompletionAttempted;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class LlmGateway
{
    private const MAX_COMPLETION_TOKENS = 400;

    public function generate(string $operation, string $instructions, string $input): string
    {
        $traceId = bin2hex(random_bytes(16));
        $messages = [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => $input],
        ];

        foreach ($this->availableProviders() as $provider) {
            try {
                return $this->complete($traceId, $operation, $provider, $messages);
            } catch (ConnectionException|RequestException $e) {
                Log::warning('LLM provider failed, trying the next one', ['provider' => $provider, 'error' => $e->getMessage()]);
            }
        }

        throw new LlmUnavailableException("No LLM provider could complete [{$operation}].");
    }

    /**
     * @return Collection<int, string>
     */
    private function availableProviders(): Collection
    {
        return collect(config('services.llm.providers'))
            ->map(fn (string $provider) => trim($provider))
            ->each(fn (string $provider) => throw_unless(
                config("services.{$provider}.base_url"),
                InvalidArgumentException::class,
                "LLM provider [{$provider}] has no base_url in config/services.php.",
            ))
            ->filter(fn (string $provider) => filled(config("services.{$provider}.key")))
            ->reject(fn (string $provider) => Cache::has($this->cooldownKey($provider)));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    private function complete(string $traceId, string $operation, string $provider, array $messages): string
    {
        $config = config("services.{$provider}");
        $startedAt = CarbonImmutable::now();

        try {
            $response = Http::withToken($config['key'])
                ->timeout(config('services.llm.timeout'))
                ->post("{$config['base_url']}/chat/completions", [
                    'model' => $config['llm_model'],
                    'messages' => $messages,
                    'max_completion_tokens' => self::MAX_COMPLETION_TOKENS,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            $this->coolDown($provider, $e);

            event(new LlmCompletionAttempted(
                traceId: $traceId,
                operation: $operation,
                provider: $provider,
                model: $config['llm_model'],
                messages: $messages,
                startedAt: $startedAt,
                finishedAt: CarbonImmutable::now(),
                error: $e->getMessage(),
            ));

            throw $e;
        }

        $output = trim((string) $response->json('choices.0.message.content'));

        event(new LlmCompletionAttempted(
            traceId: $traceId,
            operation: $operation,
            provider: $provider,
            model: $response->json('model', $config['llm_model']),
            messages: $messages,
            startedAt: $startedAt,
            finishedAt: CarbonImmutable::now(),
            output: $output,
            usage: array_filter([
                'input' => $response->json('usage.prompt_tokens'),
                'output' => $response->json('usage.completion_tokens'),
                'total' => $response->json('usage.total_tokens'),
            ], fn (?int $tokens) => $tokens !== null),
        ));

        return $output;
    }

    private function coolDown(string $provider, ConnectionException|RequestException $exception): void
    {
        $response = $exception instanceof RequestException ? $exception->response : null;

        if ($response && ! $this->isTransient($response)) {
            return;
        }

        $seconds = (int) $response?->header('Retry-After') ?: config('services.llm.cooldown');

        Cache::put($this->cooldownKey($provider), true, now()->addSeconds($seconds));
    }

    private function isTransient(Response $response): bool
    {
        return $response->tooManyRequests() || $response->serverError();
    }

    private function cooldownKey(string $provider): string
    {
        return "llm-cooldown:{$provider}";
    }
}
