<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('genres', static function (Blueprint $table) {
            $table->unsignedInteger('count')->default(0);
        });
        Schema::table('languages', static function (Blueprint $table) {
            $table->unsignedInteger('count')->default(0);
        });
        Schema::table('keywords', static function (Blueprint $table) {
            $table->unsignedInteger('count')->default(0);
        });
        Schema::table('production_companies', static function (Blueprint $table) {
            $table->unsignedInteger('count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('genres', static function (Blueprint $table) {
            $table->dropColumn('count');
        });
        Schema::table('languages', static function (Blueprint $table) {
            $table->dropColumn('count');
        });
        Schema::table('keywords', static function (Blueprint $table) {
            $table->dropColumn('count');
        });
        Schema::table('production_companies', static function (Blueprint $table) {
            $table->dropColumn('count');
        });
    }
};
