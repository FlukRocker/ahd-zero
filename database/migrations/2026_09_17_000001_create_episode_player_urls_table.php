<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episode_player_urls', function (Blueprint $table): void {
            // No FK: yu_anime_list is imported and shared with kurokami. The
            // list_id is the primary key because an episode has exactly one
            // player URL, and the backfill upserts on it.
            $table->unsignedInteger('list_id')->primary();

            // Null means "resolved, but akuma-stream has nothing ready for this
            // episode" — a real answer, not a missing one. `resolved_at` is what
            // tells the two apart.
            $table->string('watch_url', 512)->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            // The sitemap streams "episodes that have a player" in list_id
            // order; this index keeps that a range scan.
            $table->index(['watch_url', 'list_id']);

            // The backfill picks up never-tried and stalest-first rows.
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_player_urls');
    }
};
