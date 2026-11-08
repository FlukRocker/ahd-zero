<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Chat lobby tables. The real ones are created in ahd-admin
        // (2026_11_07_000001_create_chat_tables.php); these exist only so the
        // in-memory sqlite test DB has them.
        //
        // NO FOREIGN KEYS HERE: this file runs before
        // 2026_03_22_000001_create_members_table, so `members` does not exist
        // yet and a constrained() column would fail the whole test suite.
        if (! Schema::hasTable('chat_members')) {
            Schema::create('chat_members', function (Blueprint $table): void {
                $table->unsignedBigInteger('member_id')->primary();
                $table->string('rank')->default('member');
                $table->unsignedInteger('balance')->default(0);
                $table->timestamp('gagged_until')->nullable();
                $table->timestamp('kicked_until')->nullable();
                $table->timestamp('banned_at')->nullable();
                $table->timestamp('banned_until')->nullable();
                $table->string('ban_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->text('body');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->unsignedBigInteger('deleted_by_admin_id')->nullable();
                $table->index('created_at');
                $table->index('member_id');
            });
        }

        if (! Schema::hasTable('chat_cosmetics')) {
            Schema::create('chat_cosmetics', function (Blueprint $table): void {
                $table->id();
                $table->string('type');
                $table->string('name');
                $table->string('slug')->unique();
                $table->json('payload');
                $table->unsignedInteger('price')->default(0);
                $table->boolean('active')->default(true);
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('chat_cosmetic_member')) {
            Schema::create('chat_cosmetic_member', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->unsignedBigInteger('cosmetic_id');
                $table->timestamp('acquired_at')->nullable();
                $table->string('source')->default('purchase');
                $table->boolean('equipped')->default(false);
                $table->unique(['member_id', 'cosmetic_id']);
            });
        }

        if (! Schema::hasTable('chat_transactions')) {
            Schema::create('chat_transactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->integer('delta');
                $table->unsignedInteger('balance_after');
                $table->string('reason');
                $table->unsignedBigInteger('cosmetic_id')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('note')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['member_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('chat_moderation_log')) {
            Schema::create('chat_moderation_log', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('actor_member_id')->nullable();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->unsignedBigInteger('target_member_id');
                $table->string('action');
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['target_member_id', 'created_at']);
            });
        }

        // Rooms (the real ones come from ahd-admin 2026_11_08_000001_create_chat_rooms).
        // The lobby row is seeded here because tests rely on id 1 existing and
        // this app never seeds it in production either.
        if (! Schema::hasTable('chat_rooms')) {
            Schema::create('chat_rooms', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 40)->unique();
                $table->string('name', 60);
                $table->string('topic', 160)->nullable();
                $table->integer('sort')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (DB::table('chat_rooms')->where('slug', 'lobby')->doesntExist()) {
            DB::table('chat_rooms')->insert([
                'id' => 1,
                'slug' => 'lobby',
                'name' => 'Lobby',
                'sort' => 0,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasColumn('chat_messages', 'room_id')) {
            Schema::table('chat_messages', function (Blueprint $table): void {
                $table->unsignedBigInteger('room_id')->default(1)->index();
            });
        }

        // Member reports (the real table is ahd-admin 2026_11_09_000001_create_reports).
        if (! Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_id');
                $table->string('target_type', 16);
                $table->string('target_id', 32);
                $table->string('reason', 16);
                $table->string('note', 500)->nullable();
                $table->string('status', 16)->default('open');
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->unique(['member_id', 'target_type', 'target_id']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('chat_moderation_log');
        Schema::dropIfExists('chat_transactions');
        Schema::dropIfExists('chat_cosmetic_member');
        Schema::dropIfExists('chat_cosmetics');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_members');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};
