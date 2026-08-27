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
        Schema::create('user_title_states', function (Blueprint $table) {
            $table->foreignId('user_id')->references('id')->on('users');
            $table->foreignId('title_id')->references('id')->on('titles');
            $table->float('rate', 1)->nullable()->default(null);
            $table->boolean('watched')->default(false);
            $table->boolean('to_watch')->default(false);
            $table->timestamp('updated_at')->useCurrent();

            $table->primary(['user_id', 'title_id']);
        });

        DB::statement('ALTER TABLE user_title_states ADD CONSTRAINT valid_rate CHECK (rate >= 0 AND rate <= 5)');

        DB::statement('CREATE INDEX user_title_states_user_id_rate_index ON user_title_states (user_id, rate) WHERE rate > 3.5');
        DB::statement('CREATE INDEX user_title_states_user_id_to_watch_index ON user_title_states (user_id) WHERE to_watch = true');
        DB::statement('CREATE INDEX user_title_states_user_id_watched_index ON user_title_states (user_id) WHERE watched = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_title_states');
    }
};
