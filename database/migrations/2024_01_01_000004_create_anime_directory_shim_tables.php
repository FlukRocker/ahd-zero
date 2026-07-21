<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shim migrations for the imported anime "directory" aux tables (studios,
 * voice_actors, staff, and the anime_studio / anime_character pivots).
 *
 * Production uses the real imported schema (managed outside Laravel), so every
 * table is guarded with Schema::hasTable() and no-ops there. These minimal
 * versions exist only so the in-memory sqlite test DB (and local CWV runs) can
 * satisfy the Studio / VoiceActor / Directory page queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('studios')) {
            Schema::create('studios', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('name_japanese')->nullable();
                $table->integer('mal_id')->nullable();
            });
        }

        if (! Schema::hasTable('voice_actors')) {
            Schema::create('voice_actors', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('name_japanese')->nullable();
                $table->string('image_url')->nullable();
                $table->string('language')->nullable();
                $table->integer('mal_id')->nullable();
            });
        }

        if (! Schema::hasTable('staff')) {
            Schema::create('staff', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('name_japanese')->nullable();
                $table->string('image_url')->nullable();
                $table->integer('mal_id')->nullable();
            });
        }

        if (! Schema::hasTable('anime_studio')) {
            Schema::create('anime_studio', function (Blueprint $table): void {
                $table->unsignedBigInteger('anime_id');
                $table->unsignedBigInteger('studio_id');
                $table->string('role')->default('studio');
                $table->index(['studio_id', 'anime_id']);
            });
        }

        if (! Schema::hasTable('anime_character')) {
            Schema::create('anime_character', function (Blueprint $table): void {
                $table->unsignedBigInteger('anime_id');
                $table->unsignedBigInteger('character_id')->nullable();
                $table->unsignedBigInteger('voice_actor_id')->nullable();
                $table->string('character_role')->nullable();
                $table->index(['voice_actor_id', 'anime_id']);
            });
        }
    }

    public function down(): void
    {
        // Test-only shims; the real tables are managed outside Laravel.
        // Never drop them on a production rollback.
        if (app()->environment('production')) {
            return;
        }

        Schema::dropIfExists('anime_character');
        Schema::dropIfExists('anime_studio');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('voice_actors');
        Schema::dropIfExists('studios');
    }
};
