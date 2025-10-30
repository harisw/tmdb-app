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
        DB::statement(<<<SQL
            create materialized view searchable_movies AS
            select
                m.id as movie_id,
                to_tsvector(
                    'simple',
                    COALESCE(m.title) || ' '	||
                    COALESCE(l.name) || ' '	||
                    COALESCE(string_agg(distinct g."name", '| '), '') || ' '	||
                    COALESCE(string_agg(distinct pc."name", '| '), '') || ' '	||
                    COALESCE(string_agg(distinct k."name", '| '), '')
                ) as document
            from movies m
            left join languages l on l.id=m.language_id
            left join genre_movie gm on gm.movie_id =m.id left join genres g on g.id=gm.genre_id
            left join movie_production_company mpc on mpc.movie_id =m.id left join production_companies pc on pc.id=mpc.production_company_id
            left join (select movie_id, keyword_id,
            row_number() over (partition by movie_id order by id) as rn
            from keyword_movie)
                km on km.movie_id=m.id and rn<6 left join keywords k on k.id=km.keyword_id
            group by m.id, l.name
        SQL
        );
        DB::statement('CREATE INDEX searchable_movies_doc_idx ON searchable_movies USING gin(document)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP materialized view if exists searchable_movies');
        Schema::dropIfExists('searchable_movies_doc_idx');
    }
};
