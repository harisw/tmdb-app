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
        Schema::create('title_search_embeddings', function (Blueprint $table) {
            $table->foreignId('title_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('document_version');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE title_search_embeddings ADD COLUMN embedding halfvec(512) NOT NULL');
        DB::statement('CREATE INDEX title_search_embeddings_hnsw_idx ON title_search_embeddings
        USING hnsw (embedding halfvec_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('title_search_embeddings');
    }
};
