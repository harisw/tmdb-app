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
        Schema::create('title_interaction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('title_id')->constrained();
            $table->string('event_type', 32);
            $table->float('value', 1)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'event_type']);
        });

        DB::statement('CREATE INDEX title_interaction_events_user_id_created_at_desc_index ON title_interaction_events (user_id, created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('title_interaction_events');
    }
};
