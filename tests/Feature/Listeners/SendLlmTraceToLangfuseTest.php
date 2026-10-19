<?php

namespace Tests\Feature\Listeners;

use App\Events\LlmCompletionAttempted;
use App\Listeners\SendLlmTraceToLangfuse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendLlmTraceToLangfuseTest extends TestCase
{
    private string $traceId = '0af7651916cd43dd8448eb211c80319c';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.langfuse.host' => 'https://cloud.langfuse.com/',
            'services.langfuse.public_key' => 'pk-lf-test',
            'services.langfuse.secret_key' => 'sk-lf-test',
        ]);
    }

    private function attempt(array $overrides = []): LlmCompletionAttempted
    {
        return new LlmCompletionAttempted(...[
            'traceId' => $this->traceId,
            'operation' => 'hyde',
            'provider' => 'openai',
            'model' => 'gpt-test',
            'messages' => [['role' => 'user', 'content' => 'lonely robot']],
            'startedAt' => CarbonImmutable::createFromTimestampMs(1_700_000_000_000),
            'finishedAt' => CarbonImmutable::createFromTimestampMs(1_700_000_000_750),
            'output' => 'WALL-E: After 700 years...',
            'usage' => ['input' => 12, 'output' => 5, 'total' => 17],
            ...$overrides,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sentSpan(): array
    {
        $request = Http::recorded()->first()[0];

        return $request['resourceSpans'][0]['scopeSpans'][0]['spans'][0];
    }

    /**
     * @param  array<string, mixed>  $span
     * @return array<string, string>
     */
    private function attributesOf(array $span): array
    {
        return collect($span['attributes'])->mapWithKeys(fn (array $attribute) => [$attribute['key'] => $attribute['value']['stringValue']])->all();
    }

    public function test_listens_for_llm_completion_attempts(): void
    {
        Event::fake();

        Event::assertListening(LlmCompletionAttempted::class, SendLlmTraceToLangfuse::class);
    }

    public function test_posts_a_generation_span_to_the_langfuse_otel_endpoint(): void
    {
        Http::fake(['cloud.langfuse.com/*' => Http::response()]);

        (new SendLlmTraceToLangfuse)->handle($this->attempt());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cloud.langfuse.com/api/public/otel/v1/traces'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('pk-lf-test:sk-lf-test'))
            && $request->hasHeader('x-langfuse-ingestion-version', '4'));

        $span = $this->sentSpan();
        $this->assertSame($this->traceId, $span['traceId']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $span['spanId']);
        $this->assertSame('1700000000000000000', $span['startTimeUnixNano']);
        $this->assertSame('1700000000750000000', $span['endTimeUnixNano']);
        $this->assertSame(['code' => 1], $span['status']);
        $this->assertSame([
            'langfuse.trace.name' => 'hyde',
            'langfuse.environment' => 'testing',
            'langfuse.observation.type' => 'generation',
            'langfuse.observation.model.name' => 'gpt-test',
            'langfuse.observation.input' => '[{"role":"user","content":"lonely robot"}]',
            'langfuse.observation.output' => '"WALL-E: After 700 years..."',
            'langfuse.observation.usage_details' => '{"input":12,"output":5,"total":17}',
            'langfuse.observation.level' => 'DEFAULT',
            'langfuse.observation.metadata.provider' => 'openai',
        ], $this->attributesOf($span));
    }

    public function test_marks_a_failed_attempt_as_an_error(): void
    {
        Http::fake(['cloud.langfuse.com/*' => Http::response()]);

        (new SendLlmTraceToLangfuse)->handle($this->attempt(['output' => null, 'usage' => [], 'error' => 'HTTP 503']));

        $span = $this->sentSpan();
        $attributes = $this->attributesOf($span);
        $this->assertSame(['code' => 2, 'message' => 'HTTP 503'], $span['status']);
        $this->assertSame('ERROR', $attributes['langfuse.observation.level']);
        $this->assertSame('HTTP 503', $attributes['langfuse.observation.status_message']);
        $this->assertArrayNotHasKey('langfuse.observation.output', $attributes);
        $this->assertArrayNotHasKey('langfuse.observation.usage_details', $attributes);
    }

    public function test_is_only_queued_when_langfuse_keys_are_configured(): void
    {
        $listener = new SendLlmTraceToLangfuse;
        $this->assertTrue($listener->shouldQueue($this->attempt()));

        config(['services.langfuse.secret_key' => null]);
        $this->assertFalse($listener->shouldQueue($this->attempt()));
    }

    public function test_sends_nothing_when_langfuse_is_not_configured(): void
    {
        config(['services.langfuse.public_key' => null]);
        Http::fake();

        event($this->attempt());

        Http::assertNothingSent();
    }
}
