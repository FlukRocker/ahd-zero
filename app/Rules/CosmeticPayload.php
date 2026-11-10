<?php

namespace App\Rules;

use App\Enums\CosmeticType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use function is_array;
use function is_bool;

/**
 * Validates a cosmetic payload at admin save time.
 *
 * This is the FIRST of two gates, not the only one: App\Support\CosmeticStyle
 * re-checks at render. This rule exists so the admin gets a useful error
 * message, not so the renderer can trust the row.
 */
class CosmeticPayload implements ValidationRule
{
    public function __construct(private readonly CosmeticType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The payload must be an object.');

            return;
        }

        if (array_key_exists('limited', $value) && ! is_bool($value['limited'])) {
            $fail('Limited must be true or false.');

            return;
        }

        if (array_key_exists('show_only', $value) && ! is_bool($value['show_only'])) {
            $fail('Show only must be true or false.');

            return;
        }

        if (array_key_exists('stock', $value) && (! is_int($value['stock']) || $value['stock'] < 0 || $value['stock'] > 1000000)) {
            $fail('Stock must be a whole number from 0 to 1,000,000, or left empty for unlimited.');

            return;
        }

        $cosmetic = new \App\Models\ChatCosmetic;
        $cosmetic->type = $this->type;
        $cosmetic->payload = $value;

        if ($this->type === CosmeticType::Smiley) {
            $code = $value['code'] ?? '';
            $asset = $value['asset'] ?? '';

            if (! is_string($code) || ! \App\Support\CosmeticStyle::isValidSmileyCode($code)) {
                $fail('The smiley code must look like (name), 2–12 word characters.');
            }

            if (! is_string($asset) || \App\Support\CosmeticStyle::assetUrl(CosmeticType::Pawn, $asset) === null) {
                $fail('The smiley asset must be a file shipped in public/chat-assets/pawns.');
            }

            return;
        }

        if (\App\Support\CosmeticStyle::for($cosmetic) === []) {
            $fail('The payload is not valid for this cosmetic type.');

            return;
        }

        if (($error = \App\Support\CosmeticStyle::extrasError($value, $this->type)) !== null) {
            $fail($error);
        }
    }
}
