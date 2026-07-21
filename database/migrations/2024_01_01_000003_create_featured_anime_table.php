<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shim migration for the featured_anime table. Production environment already
 * has this table (curated home page "recommended"/"popular" picks, managed
 * outside Laravel migrations); this minimal version exists so Laravel's
 * in-memory sqlite test DB can satisfy FeaturedAnime model queries. See
 * create_yu_anime_catagory_table for context on the shim pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('featured_anime')) {
            return;
        }

        Schema::create('featured_anime', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('anime_id');
            $table->string('type');
            $table->integer('sort_order')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        // This is a test-only shim; the real featured_anime table is managed
        // outside Laravel. Never drop it on a production rollback.
        if (app()->environment('production')) {
            return;
        }

        Schema::dropIfExists('featured_anime');
    }
};
