<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('title_providers', function (Blueprint $table) {
            $table->index(['provider_id', 'title_id']);
        });
    }

    public function down(): void
    {
        Schema::table('title_providers', function (Blueprint $table) {
            $table->dropIndex(['provider_id', 'title_id']);
        });
    }
};
