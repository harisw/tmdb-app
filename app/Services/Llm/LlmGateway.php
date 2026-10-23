<?php

namespace App\Services\Llm;

use App\Events\LlmOperationCompleted;
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

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function generate(string $operation, string $instructions, string $input, array $parameters = []): string
    {
        $startedAt = CarbonImmutable::now();
        $messages = [
            ['role' => 'system', 'content' => $instructions],
            ['role' => 'user', 'content' => $input],
        ];
        $attempts = [];

        foreach ($this->availableProviders() as $provider) {
            $attempts[] = $attempt = $this->attempt($provider, $messages, $parameters);

            if ($attempt->succeeded()) {
                break;
            }
        }

        $completed = new LlmOperationCompleted($operation, $input, $startedAt, CarbonImmutable::now(), $attempts);
        event($completed);

        return $completed->output() ?? throw new LlmUnavailableException("No LLM provider could complete [{$operation}].");
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
     * @param  array<string, mixed>  $operationParameters
     */
    private function attempt(string $provider, array $messages, array $operationParameters): LlmAttempt
    {
        $config = config("services.{$provider}");
        $parameters = [
            'max_completion_tokens' => self::MAX_COMPLETION_TOKENS,
            ...($config['llm_parameters'] ?? []),
            ...$operationParameters,
        ];
        $startedAt = CarbonImmutable::now();

        try {
            $response = Http::keepAlive()
                ->withToken($config['key'])
                ->timeout(config('services.llm.timeout'))
                ->post("{$config['base_url']}/chat/completions", [
                    'model' => $config['llm_model'],
                    'messages' => $messages,
                    ...$parameters,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            $this->coolDown($provider, $e);
            Log::warning('LLM provider failed, trying the next one', ['provider' => $provider, 'error' => $e->getMessage()]);

            return new LlmAttempt(
                provider: $provider,
                model: $config['llm_model'],
                messages: $messages,
                parameters: $parameters,
                startedAt: $startedAt,
                finishedAt: CarbonImmutable::now(),
                error: $e->getMessage(),
            );
        }

        $output = trim((string) $response->json('choices.0.message.content'));
        $finishReason = $response->json('choices.0.finish_reason');
        $rejection = $this->rejectionOf($output, $parameters);

        if ($rejection !== null) {
            Log::warning('LLM provider returned an unusable completion, trying the next one', [
                'provider' => $provider,
                'rejection' => $rejection,
                'finish_reason' => $finishReason,
            ]);
        }

        return new LlmAttempt(
            provider: $provider,
            model: $response->json('model', $config['llm_model']),
            messages: $messages,
            parameters: $parameters,
            startedAt: $startedAt,
            finishedAt: CarbonImmutable::now(),
            output: $output === '' ? null : $output,
            usage: array_filter([
                'input' => $response->json('usage.prompt_tokens'),
                'output' => $response->json('usage.completion_tokens'),
                'total' => $response->json('usage.total_tokens'),
            ], fn (?int $tokens) => $tokens !== null),
            finishReason: $finishReason,
            error: $rejection === null ? null : "{$rejection} (finish_reason: ".($finishReason ?? 'unknown').').',
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function rejectionOf(string $output, array $parameters): ?string
    {
        return match (true) {
            $output === '' => 'Empty completion',
            $this->expectsJson($parameters) && ! json_validate($output) => 'Invalid JSON completion: '.json_last_error_msg(),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function expectsJson(array $parameters): bool
    {
        return in_array($parameters['response_format']['type'] ?? null, ['json_object', 'json_schema'], true);
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
