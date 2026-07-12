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
        Schema::table('titles', static function (Blueprint $table) {
            $table->timestamp('credits_filled_at')->after('enriched_at')->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('titles', static function (Blueprint $table) {
            $table->dropColumn('credits_filled_at');
        });
    }
};
