<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shim migrations for the imported `tags` / `taggables` tables that back
 * Anime::genres().
 *
 * Production uses the real imported schema (managed outside Laravel), so both
 * tables are guarded with Schema::hasTable() and no-op there. These minimal
 * versions exist only so the in-memory sqlite test DB can satisfy the genre
 * sidebar and /genre/{slug} queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->string('type')->nullable();
                $table->string('name_th')->nullable();
                $table->integer('order_column')->nullable();
            });
        }

        if (! Schema::hasTable('taggables')) {
            Schema::create('taggables', function (Blueprint $table): void {
                $table->unsignedBigInteger('tag_id');
                $table->unsignedBigInteger('taggable_id');
                $table->string('taggable_type');

                $table->index(['taggable_id', 'taggable_type']);
                $table->index('tag_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
