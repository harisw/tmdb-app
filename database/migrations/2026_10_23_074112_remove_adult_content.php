<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $adultTitleIds = DB::table('titles')->where('is_adult', true)->select('id');

        DB::table('user_title_states')->whereIn('title_id', $adultTitleIds)->delete();
        DB::table('title_interaction_events')->whereIn('title_id', $adultTitleIds)->delete();
        DB::table('titles')->where('is_adult', true)->delete();

        Schema::table('titles', static function (Blueprint $table) {
            $table->dropColumn('is_adult');
        });
        Schema::table('users', static function (Blueprint $table) {
            $table->dropColumn('hide_adult');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table) {
            $table->boolean('hide_adult')->default(true);
        });
        Schema::table('titles', static function (Blueprint $table) {
            $table->boolean('is_adult')->default(false);
        });
    }
};
