<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin access used to be derived from `username === ADMIN_USERNAME`, but
     * usernames are user-editable via /profile — so on any database where that
     * name was unclaimed, a registered user could rename themselves into full
     * Horizon/Pulse/feature-flag access. Authorization now lives in a column
     * that no HTTP request path can write.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('hide_adult');
        });

        // Carry over the existing admin so access is not interrupted.
        if ($username = config('app.admin_username')) {
            DB::table('users')->where('username', $username)->update(['is_admin' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
