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
        Schema::table('title_providers', static function (Blueprint $table) {
            $table->dropForeign(['country_provider_id']);
            $table->dropForeign(['country_id']);
            $table->dropForeign(['provider_id']);
            $table->dropForeign(['title_id']);

            $table->foreign('country_provider_id')->references('id')->on('country_providers')->cascadeOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->foreign('title_id')->references('id')->on('titles')->cascadeOnDelete();
        });

        Schema::table('title_provider_languages', static function (Blueprint $table) {
            $table->dropForeign(['title_provider_id']);
            $table->dropForeign(['language_id']);

            $table->foreign('title_provider_id')->references('id')->on('title_providers')->cascadeOnDelete();
            $table->foreign('language_id')->references('id')->on('languages')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('title_provider_languages', static function (Blueprint $table) {
            $table->dropForeign(['title_provider_id']);
            $table->dropForeign(['language_id']);

            $table->foreign('title_provider_id')->references('id')->on('title_providers');
            $table->foreign('language_id')->references('id')->on('languages');
        });

        Schema::table('title_providers', static function (Blueprint $table) {
            $table->dropForeign(['country_provider_id']);
            $table->dropForeign(['country_id']);
            $table->dropForeign(['provider_id']);
            $table->dropForeign(['title_id']);

            $table->foreign('country_provider_id')->references('id')->on('country_providers');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('provider_id')->references('id')->on('providers');
            $table->foreign('title_id')->references('id')->on('titles');
        });
    }
};
