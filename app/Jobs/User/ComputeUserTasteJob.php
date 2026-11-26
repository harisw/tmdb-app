<?php

namespace App\Jobs\User;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DebounceFor;

#[DebounceFor(30, maxWait: 120)]
class ComputeUserTasteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [5, 30, 60];

    public function __construct(public readonly int $userId) {}

    public function debounceId(): string
    {
        return (string) $this->userId;
    }

    public function handle(UserService $users): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $users->computeUserTaste($user);
    }
}
