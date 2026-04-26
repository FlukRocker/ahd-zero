<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function config;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Verifies Cloudflare Turnstile challenge tokens server-side.
 *
 * Frontend renders the widget (challenges.cloudflare.com/turnstile/v0/api.js)
 * which produces a one-shot token POSTed alongside the form. This service
 * exchanges the token with Cloudflare's siteverify endpoint and returns
 * true/false. Failure modes (network error, expired token, missing config)
 * all collapse to false so the controller can return a generic error.
 *
 * Disabled gracefully when secret_key is null — local dev / CI never
 * hits Cloudflare and the verifier returns true.
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        $secret = (string) config('services.turnstile.secret_key', '');

        // No secret configured = Turnstile disabled. Treat as pass so the
        // app boots in environments without Cloudflare credentials (local,
        // CI, staging without the bind). Production must set the env.
        if ($secret === '') {
            return true;
        }

        if (! is_string($token) || $token === '') {
            return false;
        }

        // remoteip is intentionally omitted. When the app sits behind a
        // proxy (Cloudflare, nginx), $request->ip() reports the proxy
        // address — which never matches the visitor IP that solved the
        // challenge, so CF returns success:false. Without remoteip, CF
        // only checks token validity (recommended for proxied apps).
        $payload = [
            'secret' => $secret,
            'response' => $token,
        ];

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_URL, $payload);
        } catch (Throwable $e) {
            // Network blip → fail closed. Better to bounce a real user than
            // silently let a bot through during a Cloudflare incident.
            Log::warning('Turnstile verify request failed', ['error' => $e->getMessage()]);

            return false;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return false;
        }

        $success = $body['success'] ?? false;
        if (! is_bool($success) || ! $success) {
            // Surface CF error codes (e.g. invalid-input-response,
            // timeout-or-duplicate, bad-request) to logs so we can
            // diagnose without enabling debug.
            Log::warning('Turnstile verify rejected', [
                'errors' => $body['error-codes'] ?? null,
                'hostname' => $body['hostname'] ?? null,
            ]);

            return false;
        }

        return true;
    }
}
