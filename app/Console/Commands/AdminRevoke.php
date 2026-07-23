<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AdminRevoke extends Command
{
    protected $signature = 'admin:revoke {username : The username to demote}';

    protected $description = 'Revoke admin access (Horizon, Pulse, feature flags) from a user';

    public function handle(): int
    {
        $username = $this->argument('username');

        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->error("No user found with username [{$username}].");

            return self::FAILURE;
        }

        if (! $user->is_admin) {
            $this->line("[{$username}] is not an admin.");

            return self::SUCCESS;
        }

        // Locking every admin out means the panel can only be recovered from a
        // shell, so make it a deliberate choice rather than a surprise.
        if (User::where('is_admin', true)->count() === 1) {
            $this->warn("[{$username}] is the only admin left.");

            if (! $this->confirm('Revoke anyway? Nobody will be able to reach Horizon, Pulse or the feature flag panel.', false)) {
                $this->line('Aborted.');

                return self::FAILURE;
            }
        }

        $user->forceFill(['is_admin' => false])->save();

        $this->info("Revoked admin access from [{$username}].");

        return self::SUCCESS;
    }
}
