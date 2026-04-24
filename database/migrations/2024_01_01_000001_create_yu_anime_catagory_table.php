<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shim migration for legacy yu_anime_catagory table. Production environment
 * uses the real imported schema with many more columns; this minimal version
 * exists so Laravel's in-memory sqlite test DB can satisfy Anime model queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('yu_anime_catagory')) {
            return;
        }

        Schema::create('yu_anime_catagory', function (Blueprint $table): void {
            $table->id('cat_id');
            $table->string('cat_title');
            $table->text('cat_desc')->nullable();
            $table->string('cat_tag')->nullable();
            $table->string('cat_image')->nullable();
            $table->integer('cat_type')->default(1);
            $table->timestamp('cat_update')->nullable();
            $table->boolean('cat_disable')->default(false);
            $table->string('title_english')->nullable();
            $table->string('title_japanese')->nullable();
            $table->string('title_synonyms')->nullable();
            $table->string('anime_type')->nullable();
            $table->integer('episodes')->nullable();
            $table->string('anime_status')->nullable();
            $table->date('aired_from')->nullable();
            $table->date('aired_to')->nullable();
            $table->string('premiered_season')->nullable();
            $table->integer('premiered_year')->nullable();
            $table->string('broadcast')->nullable();
            $table->string('source')->nullable();
            $table->string('duration')->nullable();
            $table->string('rating')->nullable();
            $table->integer('mal_id')->nullable();
            $table->json('opening_themes')->nullable();
            $table->json('ending_themes')->nullable();
            $table->string('review_url')->nullable();
            $table->integer('series_id')->nullable();
            $table->integer('series_order')->nullable();
            $table->string('anime_slug')->nullable();
            $table->text('anime_tags')->nullable();
            $table->string('cat_banner')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yu_anime_catagory');
    }
};
