<?php

namespace App\Events;

use App\Services\Llm\LlmAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

class LlmOperationCompleted
{
    use Dispatchable;

    /**
     * @param  array<int, LlmAttempt>  $attempts
     */
    public function __construct(
        public readonly string $operation,
        public readonly string $input,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $finishedAt,
        public readonly array $attempts,
    ) {}

    public function output(): ?string
    {
        return collect($this->attempts)->first(fn (LlmAttempt $attempt) => $attempt->succeeded())?->output;
    }

    public function succeeded(): bool
    {
        return $this->output() !== null;
    }
}
