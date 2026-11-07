<?php

namespace App\Rules;

use App\Enums\CosmeticType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

use function is_array;

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
                $fail('The smiley asset must be a file shipped in public/chat/pawns.');
            }

            return;
        }

        if (\App\Support\CosmeticStyle::for($cosmetic) === []) {
            $fail('The payload is not valid for this cosmetic type.');
        }
    }
}
