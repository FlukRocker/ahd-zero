<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

use function abort;
use function response;

class BookmarkController extends Controller
{
    /**
     * Idempotent: firstOrCreate plus the unique index means a double-tap or a
     * retried request returns 200 instead of a duplicate-key 500.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cat_id' => ['required', 'integer', 'exists:yu_anime_catagory,cat_id'],
        ]);

        Bookmark::firstOrCreate([
            'member_id' => $this->member()->id,
            'cat_id' => (int) $data['cat_id'],
        ]);

        return response()->json(['bookmarked' => true]);
    }

    /**
     * Scoped to the authenticated member, so one member's request can never
     * touch another's row. Deleting a non-existent bookmark is a no-op, not an
     * error — the client's desired end state is already true.
     */
    public function destroy(int $catId): JsonResponse
    {
        Bookmark::query()
            ->where('member_id', $this->member()->id)
            ->where('cat_id', $catId)
            ->delete();

        return response()->json(['bookmarked' => false]);
    }

    public function index(): View
    {
        /** @var LengthAwarePaginator<int, Bookmark> $bookmarks */
        $bookmarks = Bookmark::query()
            ->where('member_id', $this->member()->id)
            ->with('anime:cat_id,cat_title,cat_image,cat_type,anime_status,episodes,anime_type,cat_banner')
            ->orderByDesc('created_at')
            ->paginate(24);

        // Anime rows can disappear upstream (kurokami owns that table); a
        // dangling bookmark is skipped rather than rendered as a broken card.
        $items = $bookmarks->getCollection()
            ->map(fn (Bookmark $b) => $b->anime)
            ->filter()
            ->values()
            ->all();

        return view('member.bookmarks', [
            'bookmarks' => $bookmarks,
            'items' => $items,
        ]);
    }

    private function member(): Member
    {
        $member = Auth::guard('member')->user();
        if (! $member instanceof Member) {
            abort(401);
        }

        return $member;
    }
}
