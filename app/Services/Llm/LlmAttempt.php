<?php

namespace App\Services\Llm;

use Carbon\CarbonImmutable;

readonly class LlmAttempt
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $parameters
     * @param  array{input?: int, output?: int, total?: int}  $usage
     */
    public function __construct(
        public string $provider,
        public string $model,
        public array $messages,
        public array $parameters,
        public CarbonImmutable $startedAt,
        public CarbonImmutable $finishedAt,
        public ?string $output = null,
        public array $usage = [],
        public ?string $finishReason = null,
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->error === null;
    }
}
