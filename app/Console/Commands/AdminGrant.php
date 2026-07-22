<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AdminGrant extends Command
{
    protected $signature = 'admin:grant {username : The username to promote}';

    protected $description = 'Grant admin access (Horizon, Pulse, feature flags) to a user';

    public function handle(): int
    {
        $username = $this->argument('username');

        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->error("No user found with username [{$username}].");

            return self::FAILURE;
        }

        if ($user->is_admin) {
            $this->line("[{$username}] is already an admin.");

            return self::SUCCESS;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info("Granted admin access to [{$username}].");

        return self::SUCCESS;
    }
}
