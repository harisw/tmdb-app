<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

class LlmCompletionAttempted
{
    use Dispatchable;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array{input?: int, output?: int, total?: int}  $usage
     */
    public function __construct(
        public readonly string $traceId,
        public readonly string $operation,
        public readonly string $provider,
        public readonly string $model,
        public readonly array $messages,
        public readonly CarbonImmutable $startedAt,
        public readonly CarbonImmutable $finishedAt,
        public readonly ?string $output = null,
        public readonly array $usage = [],
        public readonly ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->error === null;
    }
}
