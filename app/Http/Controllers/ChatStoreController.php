<?php

namespace App\Http\Controllers;

use App\Exceptions\NotOwnedException;
use App\Exceptions\StoreException;
use App\Models\ChatCosmetic;
use App\Models\ChatCosmeticMember;
use App\Models\ChatMember;
use App\Models\Member;
use App\Services\Chat\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatStoreController extends Controller
{
    public function __construct(private readonly StoreService $store) {}

    public function index(): View
    {
        $member = $this->member();

        return view('chat.store', [
            'cosmetics' => ChatCosmetic::query()->where('active', true)->orderBy('sort')->get(),
            'owned' => ChatCosmeticMember::query()->where('member_id', $member->id)->pluck('cosmetic_id'),
            'balance' => ChatMember::query()->whereKey($member->id)->value('balance') ?? 0,
        ]);
    }

    public function buy(ChatCosmetic $cosmetic): JsonResponse
    {
        try {
            $this->store->buy($this->member()->id, $cosmetic);
        } catch (StoreException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function equip(ChatCosmetic $cosmetic): JsonResponse
    {
        try {
            $this->store->equip($this->member()->id, $cosmetic);
        } catch (NotOwnedException $e) {
            // Not owning it is an authorisation failure, not a validation one.
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (StoreException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    private function member(): Member
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return $member;
    }
}
