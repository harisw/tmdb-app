<?php

namespace Tests\Feature\Service\Llm;

use App\Events\LlmOperationCompleted;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\LlmUnavailableException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class LlmGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.llm.providers' => ['openai', 'groq'],
            'services.llm.cooldown' => 60,
            'services.openai.key' => 'openai-key',
            'services.openai.llm_model' => 'gpt-test',
            'services.openai.llm_parameters' => [],
            'services.groq.key' => 'groq-key',
            'services.groq.llm_model' => 'llama-test',
            'services.groq.llm_parameters' => ['reasoning_effort' => 'low'],
        ]);
    }

    private function gateway(): LlmGateway
    {
        return app(LlmGateway::class);
    }

    private function completion(string $content): PromiseInterface
    {
        return Http::response([
            'model' => 'served-model',
            'choices' => [['message' => ['role' => 'assistant', 'content' => $content]]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 5, 'total_tokens' => 17],
        ]);
    }

    private function requestsTo(string $host): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), $host))->count();
    }

    public function test_returns_the_first_provider_completion(): void
    {
        Http::fake(['api.openai.com/*' => $this->completion("  A catalogue entry.\n")]);

        $this->assertSame('A catalogue entry.', $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer openai-key')
            && $request['model'] === 'gpt-test'
            && $request['messages'] === [
                ['role' => 'system', 'content' => 'Be helpful.'],
                ['role' => 'user', 'content' => 'lonely robot'],
            ]);
        $this->assertSame(0, $this->requestsTo('api.groq.com'));
    }

    public function test_fails_over_to_the_next_provider_when_one_errors(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('server error', 500),
            'api.groq.com/*' => $this->completion('From Groq.'),
        ]);

        $this->assertSame('From Groq.', $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot'));

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer groq-key')
            && $request['model'] === 'llama-test');
    }

    public function test_follows_the_configured_provider_order(): void
    {
        config(['services.llm.providers' => ['groq', 'openai']]);
        Http::fake(['api.groq.com/*' => $this->completion('From Groq.')]);

        $this->assertSame('From Groq.', $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot'));
        $this->assertSame(0, $this->requestsTo('api.openai.com'));
    }

    public function test_sends_each_provider_its_own_request_parameters(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('server error', 500),
            'api.groq.com/*' => $this->completion('From Groq.'),
        ]);

        $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.groq.com')
            && $request['reasoning_effort'] === 'low'
            && $request['max_completion_tokens'] === 400);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.openai.com')
            && ! isset($request['reasoning_effort']));
    }

    public function test_adds_per_call_parameters_on_top_of_the_provider_parameters(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('server error', 500),
            'api.groq.com/*' => $this->completion('{}'),
        ]);
        $responseFormat = ['type' => 'json_object'];

        $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot', ['response_format' => $responseFormat]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.openai.com')
            && $request['response_format'] === $responseFormat);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.groq.com')
            && $request['response_format'] === $responseFormat
            && $request['reasoning_effort'] === 'low'
            && $request['max_completion_tokens'] === 400);
    }

    public function test_fails_over_when_a_provider_returns_an_empty_completion(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => ''], 'finish_reason' => 'length']],
            ]),
            'api.groq.com/*' => $this->completion('From Groq.'),
        ]);

        $this->assertSame('From Groq.', $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot'));
        $this->assertFalse(Cache::has('llm-cooldown:openai'));
    }

    public function test_fails_over_when_a_provider_returns_invalid_json_for_a_json_response_format(): void
    {
        Event::fake([LlmOperationCompleted::class]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => '{"title": "WALL'], 'finish_reason' => 'length']],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 400, 'total_tokens' => 412],
            ]),
            'api.groq.com/*' => $this->completion('{"title": "WALL·E"}'),
        ]);

        $output = $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot', ['response_format' => ['type' => 'json_schema']]);

        $this->assertSame('{"title": "WALL·E"}', $output);
        $this->assertFalse(Cache::has('llm-cooldown:openai'));
        Event::assertDispatched(LlmOperationCompleted::class, function (LlmOperationCompleted $event) {
            [$rejected] = $event->attempts;

            return ! $rejected->succeeded()
                && $rejected->output === '{"title": "WALL'
                && $rejected->finishReason === 'length'
                && $rejected->usage['output'] === 400
                && str_starts_with($rejected->error, 'Invalid JSON completion: ')
                && str_ends_with($rejected->error, ' (finish_reason: length).');
        });
    }

    public function test_accepts_plain_text_when_no_json_response_format_is_requested(): void
    {
        Http::fake(['api.openai.com/*' => $this->completion('{"title": "WALL')]);

        $this->assertSame('{"title": "WALL', $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot'));
    }

    public function test_throws_when_every_provider_returns_invalid_json(): void
    {
        Http::fake(['*' => $this->completion('not json')]);

        $this->expectException(LlmUnavailableException::class);

        $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot', ['response_format' => ['type' => 'json_object']]);
    }

    public function test_throws_when_every_provider_returns_an_empty_completion(): void
    {
        Http::fake(['*' => $this->completion('  ')]);

        $this->expectException(LlmUnavailableException::class);

        $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot');
    }

    public function test_skips_a_rate_limited_provider_until_its_retry_after_passes(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('slow down', 429, ['Retry-After' => '30']),
            'api.groq.com/*' => $this->completion('From Groq.'),
        ]);

        $this->gateway()->generate('hyde', 'Be helpful.', 'first');
        $this->gateway()->generate('hyde', 'Be helpful.', 'second');
        $this->assertSame(1, $this->requestsTo('api.openai.com'));

        $this->travel(31)->seconds();
        $this->gateway()->generate('hyde', 'Be helpful.', 'third');
        $this->assertSame(2, $this->requestsTo('api.openai.com'));
    }

    public function test_cools_down_a_failing_provider_for_the_configured_period(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('server error', 503),
            'api.groq.com/*' => $this->completion('From Groq.'),
        ]);

        $this->gateway()->generate('hyde', 'Be helpful.', 'first');
        $this->travel(59)->seconds();
        $this->gateway()->generate('hyde', 'Be helpful.', 'second');

        $this->assertSame(1, $this->requestsTo('api.openai.com'));
    }

    public function test_does_not_cool_down_a_provider_after_a_client_error(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('bad request', 400),
            'api.groq.com/*' => $this->completion('From Groq.'),
        ]);

        $this->gateway()->generate('hyde', 'Be helpful.', 'first');
        $this->gateway()->generate('hyde', 'Be helpful.', 'second');

        $this->assertSame(2, $this->requestsTo('api.openai.com'));
    }

    public function test_skips_providers_without_an_api_key(): void
    {
        config(['services.openai.key' => null]);
        Http::fake(['api.groq.com/*' => $this->completion('From Groq.')]);

        $this->assertSame('From Groq.', $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot'));
        $this->assertSame(0, $this->requestsTo('api.openai.com'));
    }

    public function test_throws_when_every_provider_fails(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('server error', 500),
            'api.groq.com/*' => Http::failedConnection(),
        ]);

        $this->expectException(LlmUnavailableException::class);

        $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot');
    }

    public function test_rejects_a_provider_missing_from_the_services_config(): void
    {
        config(['services.llm.providers' => ['opneai']]);

        $this->expectException(InvalidArgumentException::class);

        $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot');
    }

    public function test_dispatches_one_operation_event_with_every_attempt(): void
    {
        Event::fake([LlmOperationCompleted::class]);
        Http::fake([
            'api.openai.com/*' => Http::response('server error', 500),
            'api.groq.com/*' => $this->completion('From Groq.'),
        ]);

        $this->gateway()->generate('generate-hypothetical-document', 'Be helpful.', 'lonely robot');

        Event::assertDispatchedTimes(LlmOperationCompleted::class, 1);
        Event::assertDispatched(LlmOperationCompleted::class, function (LlmOperationCompleted $event) {
            [$failed, $succeeded] = $event->attempts;

            return $event->operation === 'generate-hypothetical-document'
                && $event->input === 'lonely robot'
                && $event->output() === 'From Groq.'
                && $event->finishedAt->greaterThanOrEqualTo($event->startedAt)
                && [$failed->provider, $failed->model, $failed->succeeded(), $failed->output] === ['openai', 'gpt-test', false, null]
                && [$succeeded->provider, $succeeded->model, $succeeded->output] === ['groq', 'served-model', 'From Groq.']
                && $succeeded->usage === ['input' => 12, 'output' => 5, 'total' => 17]
                && $succeeded->parameters === ['max_completion_tokens' => 400, 'reasoning_effort' => 'low'];
        });
    }

    public function test_dispatches_a_failed_operation_event_before_throwing(): void
    {
        Event::fake([LlmOperationCompleted::class]);
        Http::fake(['*' => Http::response('server error', 500)]);

        try {
            $this->gateway()->generate('generate-hypothetical-document', 'Be helpful.', 'lonely robot');
            $this->fail('Expected the gateway to throw.');
        } catch (LlmUnavailableException) {
            Event::assertDispatched(LlmOperationCompleted::class, fn (LlmOperationCompleted $event) => ! $event->succeeded()
                && count($event->attempts) === 2);
        }
    }
}
