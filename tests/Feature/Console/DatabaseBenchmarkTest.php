<?php

namespace Tests\Feature\Console;

use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_to_run_outside_a_benchmark_database(): void
    {
        $title = Title::factory()->create();

        $this->artisan('db:benchmark')
            ->expectsOutputToContain('_benchmark')
            ->assertFailed();

        $this->assertModelExists($title);
    }
}
