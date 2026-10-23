<?php

namespace Tests\Feature\Listeners;

use App\Events\LlmOperationCompleted;
use App\Listeners\SendLlmTraceToLangfuse;
use App\Services\Llm\LlmAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendLlmTraceToLangfuseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.langfuse.base_url' => 'https://cloud.langfuse.com/',
            'services.langfuse.public_key' => 'pk-lf-test',
            'services.langfuse.secret_key' => 'sk-lf-test',
        ]);
    }

    private function at(int $milliseconds): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampMs(1_700_000_000_000 + $milliseconds);
    }

    private function attempt(array $overrides = []): LlmAttempt
    {
        return new LlmAttempt(...[
            'provider' => 'openai',
            'model' => 'gpt-test',
            'messages' => [['role' => 'user', 'content' => 'lonely robot']],
            'parameters' => ['max_completion_tokens' => 400],
            'startedAt' => $this->at(10),
            'finishedAt' => $this->at(740),
            'output' => 'WALL-E: After 700 years...',
            'usage' => ['input' => 12, 'output' => 5, 'total' => 17],
            ...$overrides,
        ]);
    }

    /**
     * @param  array<int, LlmAttempt>  $attempts
     */
    private function operation(array $attempts): LlmOperationCompleted
    {
        return new LlmOperationCompleted('generate-hypothetical-document', 'lonely robot', $this->at(0), $this->at(750), $attempts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sentSpans(): array
    {
        return Http::recorded()->first()[0]['resourceSpans'][0]['scopeSpans'][0]['spans'];
    }

    /**
     * @param  array<string, mixed>  $span
     * @return array<string, string>
     */
    private function attributesOf(array $span): array
    {
        return collect($span['attributes'])->mapWithKeys(fn (array $attribute) => [$attribute['key'] => $attribute['value']['stringValue']])->all();
    }

    public function test_listens_for_completed_llm_operations(): void
    {
        Event::fake();

        Event::assertListening(LlmOperationCompleted::class, SendLlmTraceToLangfuse::class);
    }

    public function test_posts_to_the_langfuse_otel_endpoint_with_v4_ingestion(): void
    {
        Http::fake(['cloud.langfuse.com/*' => Http::response()]);

        (new SendLlmTraceToLangfuse)->handle($this->operation([$this->attempt()]));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://cloud.langfuse.com/api/public/otel/v1/traces'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('pk-lf-test:sk-lf-test'))
            && $request->hasHeader('x-langfuse-ingestion-version', '4'));
    }

    public function test_exports_a_root_span_with_the_operation_input_and_output(): void
    {
        Http::fake(['cloud.langfuse.com/*' => Http::response()]);

        (new SendLlmTraceToLangfuse)->handle($this->operation([$this->attempt()]));

        [$root] = $this->sentSpans();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $root['traceId']);
        $this->assertArrayNotHasKey('parentSpanId', $root);
        $this->assertSame('generate-hypothetical-document', $root['name']);
        $this->assertSame(['1700000000000000000', '1700000000750000000'], [$root['startTimeUnixNano'], $root['endTimeUnixNano']]);
        $this->assertSame(['code' => 1], $root['status']);
        $this->assertSame([
            'langfuse.trace.name' => 'generate-hypothetical-document',
            'langfuse.environment' => 'testing',
            'langfuse.observation.type' => 'span',
            'langfuse.observation.input' => '"lonely robot"',
            'langfuse.observation.output' => '"WALL-E: After 700 years..."',
            'langfuse.observation.level' => 'DEFAULT',
            'langfuse.observation.metadata.attempts' => '1',
        ], $this->attributesOf($root));
    }

    public function test_nests_a_generation_span_per_attempt_under_the_root(): void
    {
        Http::fake(['cloud.langfuse.com/*' => Http::response()]);

        (new SendLlmTraceToLangfuse)->handle($this->operation([
            $this->attempt(['output' => null, 'usage' => [], 'error' => 'HTTP 503', 'finishedAt' => $this->at(20)]),
            $this->attempt(['provider' => 'groq', 'model' => 'llama-test', 'startedAt' => $this->at(20)]),
        ]));

        [$root, $failed, $succeeded] = $this->sentSpans();
        foreach ([$failed, $succeeded] as $generation) {
            $this->assertSame($root['traceId'], $generation['traceId']);
            $this->assertSame($root['spanId'], $generation['parentSpanId']);
            $this->assertSame('chat-completion', $generation['name']);
        }
        $this->assertNotSame($failed['spanId'], $succeeded['spanId']);

        $this->assertSame(['code' => 2, 'message' => 'HTTP 503'], $failed['status']);
        $failedAttributes = $this->attributesOf($failed);
        $this->assertSame('ERROR', $failedAttributes['langfuse.observation.level']);
        $this->assertSame('HTTP 503', $failedAttributes['langfuse.observation.status_message']);
        $this->assertArrayNotHasKey('langfuse.observation.output', $failedAttributes);
        $this->assertArrayNotHasKey('langfuse.observation.usage_details', $failedAttributes);

        $this->assertSame(['code' => 1], $succeeded['status']);
        $this->assertSame([
            'langfuse.trace.name' => 'generate-hypothetical-document',
            'langfuse.environment' => 'testing',
            'langfuse.observation.type' => 'generation',
            'langfuse.observation.model.name' => 'llama-test',
            'langfuse.observation.model.parameters' => '{"max_completion_tokens":400}',
            'langfuse.observation.input' => '[{"role":"user","content":"lonely robot"}]',
            'langfuse.observation.output' => '"WALL-E: After 700 years..."',
            'langfuse.observation.usage_details' => '{"input":12,"output":5,"total":17}',
            'langfuse.observation.level' => 'DEFAULT',
            'langfuse.observation.metadata.provider' => 'groq',
        ], $this->attributesOf($succeeded));
    }

    public function test_exports_a_rejected_completion_with_its_output_and_finish_reason(): void
    {
        Http::fake(['cloud.langfuse.com/*' => Http::response()]);

        (new SendLlmTraceToLangfuse)->handle($this->operation([
            $this->attempt([
                'output' => '{"title": "WALL',
                'finishReason' => 'length',
                'error' => 'Invalid JSON completion: Syntax error (finish_reason: length).',
            ]),
            $this->attempt(['provider' => 'groq', 'finishReason' => 'stop']),
        ]));

        [$root, $rejected, $succeeded] = $this->sentSpans();
        $rejectedAttributes = $this->attributesOf($rejected);
        $this->assertSame(['code' => 2, 'message' => 'Invalid JSON completion: Syntax error (finish_reason: length).'], $rejected['status']);
        $this->assertSame('ERROR', $rejectedAttributes['langfuse.observation.level']);
        $this->assertSame('"{\\"title\\": \\"WALL"', $rejectedAttributes['langfuse.observation.output']);
        $this->assertSame('length', $rejectedAttributes['langfuse.observation.metadata.finish_reason']);
        $this->assertSame('{"input":12,"output":5,"total":17}', $rejectedAttributes['langfuse.observation.usage_details']);
        $this->assertSame('stop', $this->attributesOf($succeeded)['langfuse.observation.metadata.finish_reason']);

        $rootAttributes = $this->attributesOf($root);
        $this->assertSame(['code' => 1], $root['status']);
        $this->assertSame('WARNING', $rootAttributes['langfuse.observation.level']);
        $this->assertSame('Succeeded after 1 failed attempt(s).', $rootAttributes['langfuse.observation.status_message']);
        $this->assertSame('"WALL-E: After 700 years..."', $rootAttributes['langfuse.observation.output']);
    }

    public function test_marks_the_root_as_an_error_when_every_attempt_failed(): void
    {
        Http::fake(['cloud.langfuse.com/*' => Http::response()]);

        (new SendLlmTraceToLangfuse)->handle($this->operation([
            $this->attempt(['output' => null, 'usage' => [], 'error' => 'HTTP 503']),
        ]));

        [$root] = $this->sentSpans();
        $attributes = $this->attributesOf($root);
        $this->assertSame(2, $root['status']['code']);
        $this->assertSame('ERROR', $attributes['langfuse.observation.level']);
        $this->assertArrayNotHasKey('langfuse.observation.output', $attributes);
    }

    public function test_is_only_queued_when_langfuse_keys_are_configured(): void
    {
        $listener = new SendLlmTraceToLangfuse;
        $this->assertTrue($listener->shouldQueue($this->operation([$this->attempt()])));

        config(['services.langfuse.secret_key' => null]);
        $this->assertFalse($listener->shouldQueue($this->operation([$this->attempt()])));
    }

    public function test_sends_nothing_when_langfuse_is_not_configured(): void
    {
        config(['services.langfuse.public_key' => null]);
        Http::fake();

        event($this->operation([$this->attempt()]));

        Http::assertNothingSent();
    }
}
