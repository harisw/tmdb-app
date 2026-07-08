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
        Schema::table('users', static function (Blueprint $table) {
            $table->boolean('hide_adult')->after('country_id')->default(true);
        });
        Schema::table('titles', static function (Blueprint $table) {
            $table->boolean('is_adult')->after('language_id')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn('hide_adult');
        });
        Schema::table('titles', static function (Blueprint $table) {
            $table->dropColumn('is_adult');
        });
    }
};
