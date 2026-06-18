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
            $table->text('google_token')->nullable()->after('google_id');
            $table->foreignId('country_id')->after('google_token')->nullable()->constrained('countries');
            $table->schemalessAttributes('extra_attributes')->after('country_id')->default('{}');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'google_id', 'google_token', 'country_id', 'extra_attributes']);
        });
    }
};
