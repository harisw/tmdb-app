<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE titles ADD COLUMN embedding vector(1536)');
        DB::statement('CREATE INDEX titles_embedding_hnsw_idx ON titles
        USING hnsw (embedding vector_cosine_ops)
        WITH (m = 16, ef_construction = 64)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS titles_embedding_hnsw_idx');
        DB::statement('ALTER TABLE titles DROP COLUMN IF EXISTS embedding');
    }
};
