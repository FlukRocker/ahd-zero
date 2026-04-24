<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use function response;

class MemberManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Member::query()->select('id', 'uuid', 'name', 'email', 'avatar', 'bio', 'email_verified_at', 'created_at', 'updated_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($request->input('status') === 'banned') {
            $query->whereNotNull('banned_at');
        } elseif ($request->input('status') === 'active') {
            $query->whereNull('banned_at');
        }

        $members = $query->orderByDesc('created_at')->paginate(20);

        return Inertia::render('admin/Members', [
            'members' => $members,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
        ]);
    }

    public function ban(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $member = Member::findOrFail($id);
        $member->update([
            'banned_at' => now(),
            'ban_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json(['message' => 'Banned']);
    }

    public function unban(int $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $member->update([
            'banned_at' => null,
            'ban_reason' => null,
        ]);

        return response()->json(['message' => 'Unbanned']);
    }

    public function destroy(int $id): JsonResponse
    {
        $member = Member::findOrFail($id);
        $member->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
