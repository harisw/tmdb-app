<?php

namespace App\Listeners;

use App\Events\LlmOperationCompleted;
use App\Services\Llm\LlmAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

class SendLlmTraceToLangfuse implements ShouldQueue
{
    private const SPAN_KIND_INTERNAL = 1;

    private const SPAN_KIND_CLIENT = 3;

    private const STATUS_OK = 1;

    private const STATUS_ERROR = 2;

    private const GENERATION_NAME = 'chat-completion';

    public function shouldQueue(LlmOperationCompleted $event): bool
    {
        return filled(config('services.langfuse.public_key')) && filled(config('services.langfuse.secret_key'));
    }

    public function handle(LlmOperationCompleted $event): void
    {
        $traceId = bin2hex(random_bytes(16));
        $rootSpanId = bin2hex(random_bytes(8));

        Http::withBasicAuth(config('services.langfuse.public_key'), config('services.langfuse.secret_key'))
            ->withHeaders(['x-langfuse-ingestion-version' => '4'])
            ->post(rtrim(config('services.langfuse.base_url'), '/').'/api/public/otel/v1/traces', [
                'resourceSpans' => [[
                    'resource' => ['attributes' => $this->attributes(['service.name' => config('app.name')])],
                    'scopeSpans' => [[
                        'scope' => ['name' => 'llm-gateway'],
                        'spans' => [
                            $this->rootSpan($event, $traceId, $rootSpanId),
                            ...collect($event->attempts)->map(fn (LlmAttempt $attempt) => $this->generationSpan($event, $attempt, $traceId, $rootSpanId))->all(),
                        ],
                    ]],
                ]],
            ])
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    private function rootSpan(LlmOperationCompleted $event, string $traceId, string $spanId): array
    {
        $error = $event->succeeded() ? null : 'No LLM provider could complete the operation.';

        return [
            'traceId' => $traceId,
            'spanId' => $spanId,
            'name' => $event->operation,
            'kind' => self::SPAN_KIND_INTERNAL,
            'startTimeUnixNano' => $this->unixNano($event->startedAt),
            'endTimeUnixNano' => $this->unixNano($event->finishedAt),
            'attributes' => $this->attributes([
                ...$this->traceAttributes($event),
                'langfuse.observation.type' => 'span',
                'langfuse.observation.input' => json_encode($event->input),
                'langfuse.observation.output' => $event->succeeded() ? json_encode($event->output()) : null,
                'langfuse.observation.level' => $error ? 'ERROR' : 'DEFAULT',
                'langfuse.observation.status_message' => $error,
                'langfuse.observation.metadata.attempts' => (string) count($event->attempts),
            ]),
            'status' => $this->status($error),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generationSpan(LlmOperationCompleted $event, LlmAttempt $attempt, string $traceId, string $parentSpanId): array
    {
        return [
            'traceId' => $traceId,
            'spanId' => bin2hex(random_bytes(8)),
            'parentSpanId' => $parentSpanId,
            'name' => self::GENERATION_NAME,
            'kind' => self::SPAN_KIND_CLIENT,
            'startTimeUnixNano' => $this->unixNano($attempt->startedAt),
            'endTimeUnixNano' => $this->unixNano($attempt->finishedAt),
            'attributes' => $this->attributes([
                ...$this->traceAttributes($event),
                'langfuse.observation.type' => 'generation',
                'langfuse.observation.model.name' => $attempt->model,
                'langfuse.observation.model.parameters' => json_encode($attempt->parameters),
                'langfuse.observation.input' => json_encode($attempt->messages),
                'langfuse.observation.output' => $attempt->succeeded() ? json_encode($attempt->output) : null,
                'langfuse.observation.usage_details' => $attempt->usage ? json_encode($attempt->usage) : null,
                'langfuse.observation.level' => $attempt->succeeded() ? 'DEFAULT' : 'ERROR',
                'langfuse.observation.status_message' => $attempt->error,
                'langfuse.observation.metadata.provider' => $attempt->provider,
            ]),
            'status' => $this->status($attempt->error),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function traceAttributes(LlmOperationCompleted $event): array
    {
        return [
            'langfuse.trace.name' => $event->operation,
            'langfuse.environment' => config('app.env'),
        ];
    }

    /**
     * @return array{code: int, message?: string}
     */
    private function status(?string $error): array
    {
        return $error === null ? ['code' => self::STATUS_OK] : ['code' => self::STATUS_ERROR, 'message' => $error];
    }

    /**
     * @param  array<string, string|null>  $attributes
     * @return array<int, array{key: string, value: array{stringValue: string}}>
     */
    private function attributes(array $attributes): array
    {
        return collect($attributes)
            ->filter(fn (?string $value) => $value !== null)
            ->map(fn (string $value, string $key) => ['key' => $key, 'value' => ['stringValue' => $value]])
            ->values()
            ->all();
    }

    private function unixNano(CarbonImmutable $time): string
    {
        return $time->format('Uu').'000';
    }
}
