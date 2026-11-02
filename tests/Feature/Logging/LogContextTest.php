<?php

namespace Tests\Feature\Logging;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Tests\TestCase;

class LogContextTest extends TestCase
{
    public function test_queued_jobs_log_with_job_metadata(): void
    {
        $jobContext = null;
        Queue::after(function () use (&$jobContext) {
            $jobContext = Context::get('job');
        });

        dispatch(fn () => null);

        $this->assertNotNull($jobContext);
        $this->assertTrue(str_starts_with($jobContext['name'], 'Closure'));
        $this->assertArrayHasKey('queue', $jobContext);
        $this->assertSame(1, $jobContext['attempt']);
        $this->assertArrayHasKey('id', $jobContext);
    }

    public function test_queued_jobs_keep_the_dispatching_request_id(): void
    {
        $requestId = null;
        Queue::after(function () use (&$requestId) {
            $requestId = Context::get('request_id');
        });

        Context::add('request_id', 'request-123');
        dispatch(fn () => null);

        $this->assertSame('request-123', $requestId);
    }

    public function test_stderr_channel_emits_single_line_json_with_stack_trace_and_context(): void
    {
        config([
            'logging.channels.stderr.handler' => TestHandler::class,
            'logging.channels.stderr.handler_with' => [],
            'logging.channels.stderr.formatter' => JsonFormatter::class,
        ]);
        Log::forgetChannel('stderr');
        Context::add('request_id', 'request-123');

        Log::channel('stderr')->error('Provider sync failed', ['exception' => new RuntimeException('boom')]);

        /** @var TestHandler $handler */
        $handler = Log::channel('stderr')->getLogger()->getHandlers()[0];
        $formatted = $handler->getRecords()[0]->formatted;
        $payload = json_decode($formatted, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, substr_count(rtrim($formatted), "\n") + 1);
        $this->assertSame('ERROR', $payload['level_name']);
        $this->assertSame(RuntimeException::class, $payload['context']['exception']['class']);
        $this->assertNotEmpty($payload['context']['exception']['trace']);
        $this->assertSame('request-123', $payload['extra']['request_id']);
    }
}
