<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            // No FK: yu_anime_catagory is imported and shared with kurokami.
            $table->unsignedInteger('cat_id');
            $table->timestamp('created_at')->nullable();

            // The database, not the controller, is what makes a double-click
            // safe.
            $table->unique(['member_id', 'cat_id']);
            $table->index(['member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_bookmarks');
    }
};
