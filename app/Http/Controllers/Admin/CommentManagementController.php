<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use function config;
use function now;
use function response;

class CommentManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $site = (string) config('app.site_key');
        $query = Comment::query()->where('site', $site)->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('body', 'LIKE', "%{$search}%")
                    ->orWhere('user_name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->input('status') === 'deleted') {
            $query->whereNotNull('deleted_by');
        } elseif ($request->input('status') === 'active') {
            $query->whereNull('deleted_by');
        }

        $comments = $query->paginate(20);

        return Inertia::render('admin/Comments', [
            'comments' => $comments,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
        ]);
    }

    /**
     * Soft-delete a single comment.
     */
    public function destroy(string $commentId): JsonResponse
    {
        $comment = Comment::query()->findOrFail($commentId);

        $comment->update([
            'body' => '',
            'deleted_by' => 'admin',
            'deleted_at' => now()->toISOString(),
            'reactions' => [],
        ]);

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Soft-delete every comment by a specific user.
     */
    public function destroyAllByUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'string'],
        ]);
        $site = (string) config('app.site_key');

        $count = Comment::query()
            ->where('site', $site)
            ->where('user_id', $validated['user_id'])
            ->whereNull('deleted_by')
            ->count();

        Comment::query()
            ->where('site', $site)
            ->where('user_id', $validated['user_id'])
            ->whereNull('deleted_by')
            ->update([
                'body' => '',
                'deleted_by' => 'admin',
                'deleted_at' => now()->toISOString(),
                'reactions' => [],
            ]);

        return response()->json(['message' => "Deleted {$count} comments"]);
    }

    /**
     * Soft-delete the offending comment, optionally wipe everything else by
     * the same user, then ban them.
     */
    public function destroyAndBan(Request $request, string $commentId): JsonResponse
    {
        $validated = $request->validate([
            'ban_reason' => ['nullable', 'string', 'max:500'],
            'delete_all' => ['nullable', 'boolean'],
        ]);

        $comment = Comment::query()->findOrFail($commentId);
        $site = (string) config('app.site_key');

        $comment->update([
            'body' => '',
            'deleted_by' => 'admin',
            'deleted_at' => now()->toISOString(),
            'reactions' => [],
        ]);

        if ($request->boolean('delete_all') && $comment->user_id !== null) {
            Comment::query()
                ->where('site', $site)
                ->where('user_id', $comment->user_id)
                ->whereNull('deleted_by')
                ->update([
                    'body' => '',
                    'deleted_by' => 'admin',
                    'deleted_at' => now()->toISOString(),
                    'reactions' => [],
                ]);
        }

        if ($comment->user_id !== null) {
            $member = Member::query()->where('uuid', $comment->user_id)->first();
            $member?->update([
                'banned_at' => now(),
                'ban_reason' => $validated['ban_reason'] ?? 'ถูกแบนจากระบบความคิดเห็น',
            ]);
        }

        return response()->json(['message' => 'Deleted and banned']);
    }
}
