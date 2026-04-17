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
        Schema::create('title_production_company', function (Blueprint $table) {
            $table->foreignId('title_id')->constrained()->onDelete('cascade');
            $table->foreignId('production_company_id')->constrained()->onDelete('cascade');
            $table->primary(['title_id', 'production_company_id']);
            $table->index('production_company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('title_production_companies');
    }
};
