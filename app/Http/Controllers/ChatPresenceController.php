<?php

namespace App\Http\Controllers;

use App\Services\Chat\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChatPresenceController extends Controller
{
    public function __construct(private readonly PresenceService $presence) {}

    /** Heartbeat from any page a member has open. */
    public function ping(Request $request): Response
    {
        $this->presence->touch($request->user('member'));

        return response()->noContent();
    }

    /** The site-wide online list. Asking also counts as being online. */
    public function online(Request $request): JsonResponse
    {
        $this->presence->touch($request->user('member'));

        return response()->json(['online' => $this->presence->online()])
            ->header('Cache-Control', 'no-store, private');
    }
}
