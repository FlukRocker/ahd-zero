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
        $this->assertSame('/chat-assets/pawns/default.svg', CosmeticStyle::assetUrl(CosmeticType::Pawn, 'default'));
    }

    public function test_background_hex_payload(): void
    {
        $this->assertSame(
            ['--chat-background' => '#112233'],
            CosmeticStyle::for($this->cosmetic(CosmeticType::Background, ['color' => '#112233'])),
        );
    }

    public function test_background_gradient_payload(): void
    {
        $this->assertSame(
            ['--chat-background' => 'linear-gradient(45deg, #ff0000, #0000ff)'],
            CosmeticStyle::for($this->cosmetic(CosmeticType::Background, [
                'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 45,
            ])),
        );
    }

    /** Review Focus #3. */
    public function test_background_rejects_css_injection(): void
    {
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::Background, [
            'color' => 'red; background:url(x)',
        ])));
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::Background, [
            'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 999,
        ])));
    }

    public function test_background_with_asset_payload_yields_nothing(): void
    {
        $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::Background, ['asset' => 'default'])));
    }

    public function test_name_gradient_unchanged(): void
    {
        $this->assertSame(
            ['--chat-name-gradient' => 'linear-gradient(10deg, #ff0000, #0000ff)'],
            CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
                'from' => '#ff0000', 'to' => '#0000ff', 'angle' => 10,
            ])),
        );
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
            ['--chat-pawn' => "url('/chat-assets/pawns/gold.svg')"],
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
        foreach ([90.5, '90', -1, null, true] as $angle) {
            $this->assertSame([], CosmeticStyle::for($this->cosmetic(CosmeticType::NameGradient, [
                'from' => '#ff0000', 'to' => '#0000ff', 'angle' => $angle,
            ])), 'angle '.var_export($angle, true));
        }

        foreach ([-1, 0, 4, 2.5, '2', null] as $strength) {
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

    public function test_icon_animation_and_extra_glow_ride_on_a_name_colour(): void
    {
        $style = CosmeticStyle::for($this->cosmetic(CosmeticType::NameColor, [
            'color' => '#ffffff', 'icon' => 'crown', 'anim' => 'pulse', 'glow' => ['color' => '#000001', 'strength' => 2],
        ]));

        $this->assertSame([
            '--chat-name-color' => '#ffffff', '--chat-glow' => '0 0 8px #000001', '--chat-icon' => 'crown', '--chat-anim' => 'pulse',
        ], $style);
    }

    public function test_a_bad_extra_is_dropped_without_breaking_the_base_style(): void
    {
        $style = CosmeticStyle::for($this->cosmetic(CosmeticType::Glow, [
            'color' => '#00ff00', 'strength' => 1, 'icon' => 'url(x)', 'anim' => 'spin-forever',
        ]));

        $this->assertSame(['--chat-glow' => '0 0 4px #00ff00'], $style);
    }

    public function test_the_save_rule_rejects_unknown_extras_with_a_specific_message(): void
    {
        $messages = [];
        $fail = function (string $m) use (&$messages): void {
            $messages[] = $m;
        };

        (new CosmeticPayload(CosmeticType::NameColor))->validate('payload', ['color' => '#ff0000', 'icon' => 'poop'], $fail);
        (new CosmeticPayload(CosmeticType::NameColor))->validate('payload', ['color' => '#ff0000', 'anim' => 'zoom'], $fail);
        (new CosmeticPayload(CosmeticType::NameColor))->validate('payload', ['color' => '#ff0000', 'glow' => ['color' => 'red', 'strength' => 9]], $fail);
        (new CosmeticPayload(CosmeticType::Background))->validate('payload', ['color' => '#ff0000', 'glow' => ['color' => '#ffffff', 'strength' => 1]], $fail);
        (new CosmeticPayload(CosmeticType::NameColor))->validate('payload', ['color' => '#ff0000', 'icon' => 'star', 'anim' => 'blink'], $fail);

        $this->assertSame(['Unknown icon.', 'Unknown animation.', 'The extra glow needs a #rrggbb colour and strength 1-3.', 'Glow can only be added to a name colour or gradient.'], array_slice($messages, 0, 4));
        $this->assertCount(4, $messages);
    }

    public function test_limited_must_be_a_boolean_and_does_not_change_the_style(): void
    {
        $messages = [];
        $fail = function (string $m) use (&$messages): void {
            $messages[] = $m;
        };

        (new CosmeticPayload(CosmeticType::Pawn))->validate('payload', ['asset' => 'gold', 'limited' => 'yes'], $fail);
        (new CosmeticPayload(CosmeticType::Pawn))->validate('payload', ['asset' => 'gold', 'limited' => true], $fail);

        $this->assertSame(['Limited must be true or false.'], $messages);
        $this->assertSame(['--chat-name-color' => '#ff8800'], CosmeticStyle::for($this->cosmetic(CosmeticType::NameColor, ['color' => '#ff8800', 'limited' => true])));
    }
}
