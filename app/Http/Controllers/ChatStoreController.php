<?php

namespace App\Http\Controllers;

use App\Enums\CosmeticType;
use App\Exceptions\NotOwnedException;
use App\Exceptions\StoreException;
use App\Models\ChatCosmetic;
use App\Models\ChatCosmeticMember;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Member;
use App\Services\Chat\ChatService;
use App\Services\Chat\PresenceService;
use App\Services\Chat\StoreService;
use App\Support\CosmeticStyle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChatStoreController extends Controller
{
    public function __construct(
        private readonly StoreService $store,
        private readonly PresenceService $presence,
        private readonly ChatService $chat,
    ) {}

    public function index(): View
    {
        $member = $this->member();

        /** @var Collection<int, ChatCosmeticMember> $inventory */
        $inventory = ChatCosmeticMember::query()->where('member_id', $member->id)->get()->keyBy('cosmetic_id');

        // Pawns have no store UI in v1.
        $items = ChatCosmetic::query()
            ->where('active', true)
            ->where('type', '!=', CosmeticType::Pawn->value)
            ->orderBy('sort')
            ->get()
            ->map(fn (ChatCosmetic $cosmetic): array => [
                'id' => $cosmetic->id,
                'type' => $cosmetic->type->value,
                'name' => $cosmetic->name,
                'price' => (int) $cosmetic->price,
                'slot' => $cosmetic->type->slot(),
                'limited' => ($cosmetic->payload['limited'] ?? false) === true,
                // The number left is never sent: members only learn "sold out".
                'show_only' => ($cosmetic->payload['show_only'] ?? false) === true,
                'sold_out' => is_int($cosmetic->payload['stock'] ?? null) && $cosmetic->payload['stock'] < 1,
                'style' => CosmeticStyle::for($cosmetic),
                'owned' => $inventory->has($cosmetic->id),
                'equipped' => (bool) $inventory->get($cosmetic->id)?->equipped,
            ])
            ->values()
            ->all();

        return view('chat.store', [
            'items' => $items,
            'balance' => $this->balance($member->id),
        ]);
    }

    public function buy(ChatCosmetic $cosmetic): JsonResponse
    {
        try {
            $this->store->buy($this->member()->id, $cosmetic);
        } catch (StoreException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'balance' => $this->balance($this->member()->id)]);
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

        $this->restyle($this->member()->id);

        return response()->json(['ok' => true]);
    }

    /** Cached history bakes in styles: rebuild it, then push the new look to open chat boxes. */
    private function restyle(int $memberId): void
    {
        foreach (ChatRoom::query()->pluck('id') as $roomId) {
            $this->chat->forgetHistory((int) $roomId);
        }

        $this->presence->announceStyle($memberId);
    }

    private function balance(int $memberId): int
    {
        return (int) (ChatMember::query()->whereKey($memberId)->value('balance') ?? 0);
    }

    private function member(): Member
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();

        return $member;
    }
}
