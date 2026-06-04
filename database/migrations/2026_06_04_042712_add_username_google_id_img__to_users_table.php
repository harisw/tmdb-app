<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('google_id')->nullable()->unique()->after('remember_token');
            $table->string('google_token')->nullable()->after('google_id');
            $table->string('image')->nullable()->after('google_token');
            $table->foreignId('country_id')->after('image')->nullable()->constrained('countries');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'google_id', 'google_token']);
        });
    }
};
