<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        (fn () => $this->hotFile = storage_path('framework/testing/vite-hot'))->call($this->app->make(Vite::class));
        Http::preventStrayRequests();
    }
}
