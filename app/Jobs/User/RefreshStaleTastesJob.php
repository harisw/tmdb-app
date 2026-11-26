<?php

namespace App\Jobs\User;

use App\Models\User;
use App\Models\UserTitleState;
use App\Services\UserService;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshStaleTastesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(private readonly int $limit = 10) {}

    public function handle(UserService $userService): void
    {
        $userIds = UserTitleState::query()
            ->leftJoin('user_tastes', 'user_tastes.user_id', '=', 'user_title_states.user_id')
            ->where(fn (Builder $query) => $query
                ->whereNull('user_tastes.user_id')
                ->orWhereColumn('user_title_states.updated_at', '>', 'user_tastes.updated_at'))
            ->groupBy('user_title_states.user_id')
            ->orderByRaw('min(user_title_states.updated_at)')
            ->limit($this->limit)
            ->pluck('user_title_states.user_id');

        Log::info("Refreshing tastes for {$userIds->count()} users");

        User::whereKey($userIds)->each(fn (User $user) => $userService->computeUserTaste($user));
    }
}
