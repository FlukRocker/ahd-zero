<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shim migration for legacy yu_anime_list (episodes) table. See
 * create_yu_anime_catagory_table for context.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('yu_anime_list')) {
            return;
        }

        Schema::create('yu_anime_list', function (Blueprint $table): void {
            $table->id('list_id');
            $table->unsignedBigInteger('catagory_id');
            $table->string('list_title');
            $table->uuid('uuid')->nullable();
            $table->string('file_src')->nullable();
            $table->string('list_url')->nullable();
            $table->timestamp('adddate')->nullable();
            $table->softDeletes();
            $table->index('catagory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yu_anime_list');
    }
};
