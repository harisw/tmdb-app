<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignRequestIdTest extends TestCase
{
    public function test_response_carries_the_request_id_added_to_log_context(): void
    {
        $response = $this->get('/up');

        $requestId = $response->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($requestId));
        $this->assertSame($requestId, Context::get('request_id'));
    }

    public function test_each_request_gets_a_new_request_id(): void
    {
        $first = $this->get('/up')->headers->get('X-Request-Id');
        $second = $this->get('/up')->headers->get('X-Request-Id');

        $this->assertNotSame($first, $second);
    }

    public function test_client_supplied_request_id_is_not_trusted(): void
    {
        $response = $this->get('/up', ['X-Request-Id' => 'spoofed']);

        $this->assertNotSame('spoofed', $response->headers->get('X-Request-Id'));
    }
}
