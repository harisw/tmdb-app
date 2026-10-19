<?php

namespace App\Listeners;

use App\Events\LlmCompletionAttempted;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;

class SendLlmTraceToLangfuse implements ShouldQueue
{
    private const SPAN_KIND_CLIENT = 3;

    private const STATUS_OK = 1;

    private const STATUS_ERROR = 2;

    public function shouldQueue(LlmCompletionAttempted $event): bool
    {
        return filled(config('services.langfuse.public_key')) && filled(config('services.langfuse.secret_key'));
    }

    public function handle(LlmCompletionAttempted $event): void
    {
        Http::withBasicAuth(config('services.langfuse.public_key'), config('services.langfuse.secret_key'))
            ->withHeaders(['x-langfuse-ingestion-version' => '4'])
            ->post(rtrim(config('services.langfuse.host'), '/').'/api/public/otel/v1/traces', [
                'resourceSpans' => [[
                    'resource' => ['attributes' => $this->attributes(['service.name' => config('app.name')])],
                    'scopeSpans' => [[
                        'scope' => ['name' => 'llm-gateway'],
                        'spans' => [$this->span($event)],
                    ]],
                ]],
            ])
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    private function span(LlmCompletionAttempted $event): array
    {
        return [
            'traceId' => $event->traceId,
            'spanId' => bin2hex(random_bytes(8)),
            'name' => "{$event->operation} via {$event->provider}",
            'kind' => self::SPAN_KIND_CLIENT,
            'startTimeUnixNano' => $this->unixNano($event->startedAt),
            'endTimeUnixNano' => $this->unixNano($event->finishedAt),
            'attributes' => $this->attributes([
                'langfuse.trace.name' => $event->operation,
                'langfuse.environment' => config('app.env'),
                'langfuse.observation.type' => 'generation',
                'langfuse.observation.model.name' => $event->model,
                'langfuse.observation.input' => json_encode($event->messages),
                'langfuse.observation.output' => $event->succeeded() ? json_encode($event->output) : null,
                'langfuse.observation.usage_details' => $event->usage ? json_encode($event->usage) : null,
                'langfuse.observation.level' => $event->succeeded() ? 'DEFAULT' : 'ERROR',
                'langfuse.observation.status_message' => $event->error,
                'langfuse.observation.metadata.provider' => $event->provider,
            ]),
            'status' => $event->succeeded()
                ? ['code' => self::STATUS_OK]
                : ['code' => self::STATUS_ERROR, 'message' => $event->error],
        ];
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
