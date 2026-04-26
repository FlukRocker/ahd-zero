<?php

namespace App\Rules;

use App\Services\TurnstileVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;

use function app;
use function is_string;

/**
 * Validation rule that verifies a Cloudflare Turnstile token via the
 * siteverify endpoint. Use as `'cf-turnstile-response' => ['required', new TurnstileToken]`.
 *
 * Skips silently when secret_key is unset (TurnstileVerifier::verify() returns
 * true) so local dev doesn't need Cloudflare creds.
 */
class TurnstileToken implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $verifier = app(TurnstileVerifier::class);
        $request = app(Request::class);

        $token = is_string($value) ? $value : '';

        if (! $verifier->verify($token, $request->ip())) {
            $fail('การยืนยันตัวตนไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
        }
    }
}
