<?php

namespace Tests\Unit\Chat;

use App\Enums\CosmeticType;
use App\Models\ChatCosmetic;
use App\Rules\CosmeticPayload;
use App\Support\CosmeticStyle;
use Tests\TestCase;

class CosmeticStyleTest extends TestCase
{
    private function cosmetic(CosmeticType $type, array $payload): ChatCosmetic
    {
        $cosmetic = new ChatCosmetic;
        $cosmetic->type = $type;
        $cosmetic->payload = $payload;

        return $cosmetic;
    }

    public function test_a_valid_colour_becomes_a_custom_property(): void
    {
        $style = CosmeticStyle::for($this->cosmetic(CosmeticType::NameColor, ['color' => '#ff8800']));

        $this->assertSame(['--chat-name-color' => '#ff8800'], $style);
    }

    public function test_a_gradient_needs_both_stops_and_an_angle(): void
    {
        $style = CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
            'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 90,
        ]));

        $this->assertSame('linear-gradient(90deg, #ff0000, #0000ff)', $style['--chat-name-gradient']);
    }

    /**
     * Review Focus #3. The row can be wrong — hand-edited, or written before
     * validation tightened — and style="..." is a CSS context where HTML
     * escaping does nothing. The renderer itself has to refuse.
     */
    public function test_a_payload_that_is_invalid_in_the_database_is_refused_at_render(): void
    {
        $injection = $this->cosmetic(CosmeticType::NameColor, [
            'color' => 'red; background: url(javascript:alert(1))',
        ]);

        $this->assertSame([], CosmeticStyle::for($injection));
    }

    public function test_an_out_of_range_gradient_angle_is_refused(): void
    {
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
            'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 999,
        ])));
    }

    public function test_an_asset_slug_cannot_traverse_out_of_its_directory(): void
    {
        $this->assertNull(CosmeticStyle::assetUrl(CosmeticType::Pawn, '../../../etc/passwd'));
        $this->assertNull(CosmeticStyle::assetUrl(CosmeticType::Pawn, 'does-not-exist'));
        $this->assertSame('/chat/pawns/default.svg', CosmeticStyle::assetUrl(CosmeticType::Pawn, 'default'));
    }

    public function test_a_smiley_code_must_look_like_a_code(): void
    {
        $this->assertTrue(CosmeticStyle::isValidSmileyCode('(akuma)'));
        $this->assertFalse(CosmeticStyle::isValidSmileyCode('<img src=x>'));
        $this->assertFalse(CosmeticStyle::isValidSmileyCode('(a)'));
    }

    public function test_every_type_refuses_hostile_values_at_render(): void
    {
        $hostile = 'red; background: url(javascript:alert(1))';

        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
            'from' => $hostile, 'to' => '#0000ff', 'angle' => 90,
        ])));
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
            'from' => '#ff0000', 'to' => '#0000ff', 'angle' => '90deg); x: url(a',
        ])));
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::Glow, [
            'color' => $hostile, 'strength' => 2,
        ])));
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::Glow, [
            'color' => '#ff0000', 'strength' => 9,
        ])));
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::Pawn, [
            'asset' => "default');background:url(x",
        ])));
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::Pawn, [
            'asset' => '../../etc/passwd',
        ])));
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::NameColor, [
            'color' => "#ff8800\n; x: y",
        ])));
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::NameColor, [])));
    }

    public function test_valid_glow_and_pawn_render(): void
    {
        $this->assertSame(
            ['--chat-glow' => '0 0 8px #ff0000'],
            CosmeticStyle::for($this->cosmetic(CosmeticType::Glow, ['color' => '#ff0000', 'strength' => 2])),
        );
        $this->assertSame(
            ['--chat-pawn' => "url('/chat/pawns/gold.svg')"],
            CosmeticStyle::for($this->cosmetic(CosmeticType::Pawn, ['asset' => 'gold'])),
        );
    }

    public function test_the_save_rule_agrees_with_the_renderer(): void
    {
        $fails = static function (CosmeticType $type, mixed $value): bool {
            $failed = false;
            (new CosmeticPayload($type))->validate('payload', $value, function () use (&$failed) {
                $failed = true;
            });

            return $failed;
        };

        $this->assertFalse($fails(CosmeticType::NameColor, ['color' => '#ff8800']));
        $this->assertTrue($fails(CosmeticType::NameColor, ['color' => 'red; x: y']));
        $this->assertTrue($fails(CosmeticType::NameColor, 'nope'));
        $this->assertFalse($fails(CosmeticType::Smiley, ['code' => '(akuma)', 'asset' => 'default']));
        $this->assertTrue($fails(CosmeticType::Smiley, ['code' => '<img>', 'asset' => 'default']));
    }

    public function test_trailing_newlines_are_refused(): void
    {
        $this->assertNull(CosmeticStyle::assetUrl(CosmeticType::Pawn, "gold\n"));
        $this->assertFalse(CosmeticStyle::isValidSmileyCode("(akuma)\n"));
    }

    public function test_gradient_angle_and_glow_strength_must_be_in_range_ints(): void
    {
        foreach ([90.0, '90', -1, null, true] as $angle) {
            $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
                'from' => '#ff0000', 'to' => '#0000ff', 'angle' => $angle,
            ])), 'angle '.var_export($angle, true));
        }

        foreach ([-1, 0, 4, 2.0, '2', null] as $strength) {
            $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::Glow, [
                'color' => '#ff0000', 'strength' => $strength,
            ])), 'strength '.var_export($strength, true));
        }
    }

    public function test_a_non_array_payload_falls_back_for_every_type(): void
    {
        foreach (CosmeticType::cases() as $type) {
            foreach ([null, 'x', 5, []] as $payload) {
                $cosmetic = new ChatCosmetic;
                $cosmetic->type = $type;
                $cosmetic->payload = $payload;

                $this->assertSame([], CosmeticStyle::for($cosmetic), $type->value.' '.var_export($payload, true));
            }
        }
    }

    public function test_the_save_rule_rejects_non_array_input_cleanly(): void
    {
        foreach (CosmeticType::cases() as $type) {
            foreach ([null, 'x', 5] as $value) {
                $failed = false;
                (new CosmeticPayload($type))->validate('payload', $value, function () use (&$failed) {
                    $failed = true;
                });
                $this->assertTrue($failed, $type->value);
            }
        }
    }
}
