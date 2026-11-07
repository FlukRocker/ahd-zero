<?php

namespace App\Enums;

/**
 * Cosmetic types and the equip slot each one occupies.
 *
 * Exclusivity belongs to the SLOT, not the type: name_color and name_gradient
 * both occupy `name`, so equipping one clears the other and two name styles can
 * never fight. A smiley has no slot — owning it makes it usable.
 */
enum CosmeticType: string
{
    case NameColor = 'name_color';
    case NameGradient = 'name_gradient';
    case Glow = 'glow';
    case Pawn = 'pawn';
    case Smiley = 'smiley';
    case Background = 'background';

    public function slot(): ?string
    {
        return match ($this) {
            self::NameColor, self::NameGradient => 'name',
            self::Glow => 'effect',
            self::Pawn => 'pawn',
            self::Background => 'background',
            self::Smiley => null,
        };
    }
}
