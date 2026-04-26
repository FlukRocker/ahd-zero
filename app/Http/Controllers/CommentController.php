<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\CommentNotification;
use App\Rules\TurnstileToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

use function abort;
use function array_filter;
use function array_values;
use function config;
use function in_array;
use function mb_substr;
use function now;
use function response;

class CommentController extends Controller
{
    public function index(string $type, int $id): JsonResponse
    {
        $commentableType = $this->resolveType($type);
        $site = (string) config('app.site_key');

        try {
            $comments = Comment::query()
                ->where('site', $site)
                ->where('commentable_type', $commentableType)
                ->where('commentable_id', $id)
                ->whereNull('parent_id')
                ->orderByDesc('created_at')
                ->paginate(20);

            // Load replies for each comment (1 level deep)
            $comments->getCollection()->transform(function (Comment $comment) use ($site): Comment {
                $comment->setRelation(
                    'replies',
                    Comment::query()
                        ->where('site', $site)
                        ->where('parent_id', $comment->id)
                        ->orderBy('created_at')
                        ->get()
                );

                return $comment;
            });

            return response()->json($comments);
        } catch (Throwable) {
            // Mongo down → empty result, never 500 the page.
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'total' => 0,
            ]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'commentable_type' => ['required', 'string', 'in:anime,episode'],
            'commentable_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'string'],
        ];

        if (config('services.turnstile.secret_key')) {
            $rules['cf-turnstile-response'] = ['required', 'string', new TurnstileToken];
        }

        $validated = $request->validate($rules);

        $user = $this->getAuthUser($request);
        $site = (string) config('app.site_key');

        $comment = Comment::create([
            'body' => $validated['body'],
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_avatar' => $user['avatar'],
            'is_admin' => $user['is_admin'],
            'commentable_type' => $this->resolveType($validated['commentable_type']),
            'commentable_id' => $validated['commentable_id'],
            'parent_id' => $validated['parent_id'] ?? null,
            'reactions' => [],
            'site' => $site,
        ]);

        // Send notification to parent comment owner on reply
        if ($comment->parent_id !== null) {
            $parent = Comment::query()->find($comment->parent_id);
            if ($parent instanceof Comment && $parent->user_id !== $user['id']) {
                CommentNotification::create([
                    'user_id' => $parent->user_id,
                    'comment_id' => $comment->id,
                    'type' => 'reply',
                    'from_user_name' => $user['name'],
                    'from_user_avatar' => $user['avatar'],
                    'message' => mb_substr($comment->body, 0, 100),
                    'read' => false,
                    'site' => $site,
                ]);
            }
        }

        return response()->json($comment, 201);
    }

    public function update(Request $request, string $commentId): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $comment = Comment::query()->findOrFail($commentId);
        $user = $this->getAuthUser($request);

        if ($comment->user_id !== $user['id'] && ! Auth::guard('web')->check()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->update(['body' => $validated['body']]);

        return response()->json($comment);
    }

    public function destroy(Request $request, string $commentId): JsonResponse
    {
        $comment = Comment::query()->findOrFail($commentId);
        $user = $this->getAuthUser($request);

        $isOwner = $comment->user_id === $user['id'];
        $isAdmin = Auth::guard('web')->check();

        if (! $isOwner && ! $isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Soft delete: keep the comment but clear body and mark who deleted it
        $comment->update([
            'body' => '',
            'deleted_by' => $isOwner ? 'author' : 'admin',
            'deleted_at' => now()->toISOString(),
            'reactions' => [],
        ]);

        return response()->json($comment);
    }

    public function react(Request $request, string $commentId): JsonResponse
    {
        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:10'],
        ]);

        $comment = Comment::query()->findOrFail($commentId);
        $user = $this->getAuthUser($request);
        $emoji = $validated['emoji'];
        $site = (string) config('app.site_key');

        /** @var array<int, array{emoji: string, user_ids: array<int, string>}> $reactions */
        $reactions = $comment->reactions ?? [];

        $found = false;
        foreach ($reactions as &$reaction) {
            if ($reaction['emoji'] === $emoji) {
                $found = true;
                if (in_array($user['id'], $reaction['user_ids'], true)) {
                    $reaction['user_ids'] = array_values(array_filter(
                        $reaction['user_ids'],
                        fn (string $uid): bool => $uid !== $user['id']
                    ));
                } else {
                    $reaction['user_ids'][] = $user['id'];
                }
                break;
            }
        }
        unset($reaction);

        if (! $found) {
            $reactions[] = ['emoji' => $emoji, 'user_ids' => [$user['id']]];
        }

        // Drop empty reactions
        $reactions = array_values(array_filter($reactions, fn (array $r): bool => $r['user_ids'] !== []));

        $comment->update(['reactions' => $reactions]);

        // Notify comment owner about reaction (if not self)
        if ($comment->user_id !== $user['id']) {
            CommentNotification::create([
                'user_id' => $comment->user_id,
                'comment_id' => $comment->id,
                'type' => 'reaction',
                'from_user_name' => $user['name'],
                'from_user_avatar' => $user['avatar'],
                'message' => $emoji,
                'read' => false,
                'site' => $site,
            ]);
        }

        return response()->json($comment);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $site = (string) config('app.site_key');

        try {
            $notifications = CommentNotification::query()
                ->where('site', $site)
                ->where('user_id', $user['id'])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            $unreadCount = CommentNotification::query()
                ->where('site', $site)
                ->where('user_id', $user['id'])
                ->where('read', false)
                ->count();

            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (Throwable) {
            return response()->json(['notifications' => [], 'unread_count' => 0]);
        }
    }

    public function markRead(Request $request): JsonResponse
    {
        $user = $this->getAuthUser($request);
        $site = (string) config('app.site_key');

        CommentNotification::query()
            ->where('site', $site)
            ->where('user_id', $user['id'])
            ->where('read', false)
            ->update(['read' => true]);

        return response()->json(['message' => 'OK']);
    }

    private function resolveType(string $type): string
    {
        return match ($type) {
            'anime' => 'App\\Models\\Anime',
            'episode' => 'App\\Models\\Episode',
            default => $type,
        };
    }

    /**
     * @return array{id: string, name: string, avatar: string|null, is_admin: bool}
     */
    private function getAuthUser(Request $request): array
    {
        $member = $request->user('member');
        if ($member !== null) {
            return [
                'id' => $member->uuid,
                'name' => $member->name,
                'avatar' => $member->avatar,
                'is_admin' => false,
            ];
        }

        $admin = $request->user('web');
        if ($admin !== null) {
            return [
                'id' => $admin->uuid ?? (string) $admin->id,
                'name' => $admin->name,
                'avatar' => null,
                'is_admin' => true,
            ];
        }

        abort(401);
    }
}
