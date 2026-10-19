<?php

namespace Tests\Feature\Service\Llm;

use App\Events\LlmCompletionAttempted;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\LlmUnavailableException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
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
            'services.groq.key' => 'groq-key',
            'services.groq.llm_model' => 'llama-test',
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

    public function test_dispatches_an_attempt_event_per_provider_tried(): void
    {
        Event::fake([LlmCompletionAttempted::class]);
        Http::fake([
            'api.openai.com/*' => Http::response('server error', 500),
            'api.groq.com/*' => $this->completion('From Groq.'),
        ]);

        $this->gateway()->generate('hyde', 'Be helpful.', 'lonely robot');

        $attempts = Event::dispatched(LlmCompletionAttempted::class)->map(fn (array $arguments) => $arguments[0]);
        $this->assertCount(2, $attempts);
        [$failed, $succeeded] = $attempts->all();

        $this->assertSame($failed->traceId, $succeeded->traceId);
        $this->assertSame(['openai', 'gpt-test', 'hyde'], [$failed->provider, $failed->model, $failed->operation]);
        $this->assertFalse($failed->succeeded());
        $this->assertNull($failed->output);

        $this->assertSame(['groq', 'served-model'], [$succeeded->provider, $succeeded->model]);
        $this->assertTrue($succeeded->succeeded());
        $this->assertSame('From Groq.', $succeeded->output);
        $this->assertSame(['input' => 12, 'output' => 5, 'total' => 17], $succeeded->usage);
        $this->assertTrue($succeeded->finishedAt->greaterThanOrEqualTo($succeeded->startedAt));
    }
}
