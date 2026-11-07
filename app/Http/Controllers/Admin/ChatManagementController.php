<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChatRank;
use App\Enums\CosmeticType;
use App\Events\Chat\MessageDeleted;
use App\Http\Controllers\Controller;
use App\Models\ChatCosmetic;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatModerationLog;
use App\Models\Member;
use App\Rules\CosmeticPayload;
use App\Services\Chat\ChatService;
use App\Services\Chat\ModerationService;
use App\Services\Chat\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard side of the chat.
 *
 * Admins have no chat rank — they are not in chat_members at all — so this
 * controller does not consult ChatPolicy. Being in the dashboard IS the
 * authorisation, exactly as it is for the existing member and comment screens.
 * In-room powers require a member account; see ChatModerationController.
 */
class ChatManagementController extends Controller
{
    public function __construct(
        private readonly StoreService $store,
        private readonly ModerationService $moderation,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/Chat', [
            'cosmetics' => ChatCosmetic::query()->orderBy('sort')->get(),
            'participants' => ChatMember::query()->with('member:id,uuid,name')->latest('updated_at')->paginate(20),
            'log' => ChatModerationLog::query()->latest('created_at')->limit(50)->get(),
        ]);
    }

    public function storeCosmetic(Request $request): JsonResponse
    {
        $type = is_string($request->input('type')) ? CosmeticType::tryFrom($request->input('type')) : null;

        if ($type === null) {
            return response()->json(['message' => 'Unknown cosmetic type.'], 422);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::enum(CosmeticType::class)],
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', 'unique:chat_cosmetics,slug'],
            'payload' => ['required', 'array', new CosmeticPayload($type)],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sort' => ['nullable', 'integer', 'min:-32768', 'max:32767'],
        ]);

        ChatCosmetic::create($validated + ['active' => true]);

        return response()->json(['ok' => true]);
    }

    public function updateCosmetic(Request $request, ChatCosmetic $cosmetic): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:60'],
            'price' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:-32768', 'max:32767'],
            'payload' => ['sometimes', 'array', new CosmeticPayload($cosmetic->type)],
        ]);

        $cosmetic->update($validated);

        return response()->json(['ok' => true]);
    }

    /** Deactivate, never delete: inventory and ledger rows must keep meaning. */
    public function destroyCosmetic(ChatCosmetic $cosmetic): JsonResponse
    {
        $cosmetic->update(['active' => false]);

        return response()->json(['ok' => true]);
    }

    public function grant(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->ensureMember($id);

        // Deliberately NOT wrapped in DB::transaction: the service creates the
        // wallet row with an autocommit insert before opening its own
        // transaction, and an outer one would defeat that ordering and bring
        // back the InnoDB deadlock it avoids.
        $this->store->grantCoins($id, $validated['amount'], (int) $request->user()->id, $validated['note'] ?? null);

        return response()->json(['ok' => true]);
    }

    public function grantCosmetic(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'cosmetic_id' => ['required', 'integer', 'exists:chat_cosmetics,id'],
        ]);

        $this->ensureMember($id);

        // Not wrapped in a transaction, same reason as grant().
        $this->store->grantCosmetic(
            $id,
            ChatCosmetic::query()->findOrFail($validated['cosmetic_id']),
            (int) $request->user()->id,
        );

        return response()->json(['ok' => true]);
    }

    public function setRank(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rank' => ['required', Rule::enum(ChatRank::class)],
        ]);

        $target = $this->walletFor($id);

        $this->moderation->setRank($target, ChatRank::from($validated['rank']), null, (int) $request->user()->id);

        return response()->json(['ok' => true]);
    }

    public function ban(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'unban' => ['sometimes', 'boolean'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $target = $this->walletFor($id);
        $adminId = (int) $request->user()->id;

        if ($validated['unban'] ?? false) {
            $this->moderation->unban($target, null, $adminId);
        } else {
            $this->moderation->ban($target, $validated['minutes'] ?? null, $validated['reason'] ?? null, null, $adminId);
        }

        return response()->json(['ok' => true]);
    }

    public function destroyMessage(Request $request, int $id): JsonResponse
    {
        $message = ChatMessage::query()->findOrFail($id);
        $adminId = (int) $request->user()->id;
        $message->deleted_by_admin_id = $adminId;
        $message->save();
        $message->delete();

        // Admins act through the web guard, not as members, so actor_member_id
        // stays null and admin_id carries the identity.
        ChatModerationLog::create([
            'actor_member_id' => null,
            'admin_id' => $adminId,
            'target_member_id' => $message->member_id,
            'action' => 'delete_message',
            'meta' => ['message_id' => $message->id],
        ]);

        app(ChatService::class)->forgetHistory();

        // The room has to hear about it too. Without this an admin delete
        // vanishes from the database but stays on every open screen until
        // the next reload.
        MessageDeleted::dispatch($message->id);

        return response()->json(['ok' => true]);
    }

    /** 404 a member id that does not exist, so no orphan wallet is created. */
    private function ensureMember(int $id): void
    {
        Member::query()->findOrFail($id);
    }

    private function walletFor(int $id): ChatMember
    {
        $this->ensureMember($id);

        return ChatMember::query()->firstOrCreate(['member_id' => $id], ['rank' => 'member', 'balance' => 0]);
    }
}
