<?php

namespace Tests\Feature\Chat;

use App\Enums\CosmeticType;
use App\Models\ChatCosmetic;
use App\Models\ChatCosmeticMember;
use App\Models\ChatMessage;
use App\Models\Member;
use App\Services\Chat\ChatService;
use App\Services\Chat\MessageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessageRenderingTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    private ChatCosmetic $smiley;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = Member::create([
            'name' => 'Rin',
            'email' => 'rin@example.test',
            'password' => 'password123!',
        ]);

        $this->smiley = ChatCosmetic::create([
            'type' => CosmeticType::Smiley,
            'name' => 'Akuma',
            'slug' => 'akuma',
            'payload' => ['code' => '(akuma)', 'asset' => 'default'],
            'price' => 50,
            'active' => true,
        ]);
    }

    private function own(ChatCosmetic $cosmetic, bool $equipped = false): void
    {
        ChatCosmeticMember::create([
            'member_id' => $this->member->id,
            'cosmetic_id' => $cosmetic->id,
            'acquired_at' => now(),
            'source' => 'purchase',
            'equipped' => $equipped,
        ]);
    }

    public function test_a_smiley_the_sender_owns_becomes_an_image_segment(): void
    {
        $this->own($this->smiley);

        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hi (akuma) there']);
        $record = $this->app->make(ChatService::class)->record($message->fresh()->load('member'));

        $this->assertSame(
            [
                ['t' => 'text', 'v' => 'hi '],
                ['t' => 'img', 'v' => '/chat/pawns/default.svg'],
                ['t' => 'text', 'v' => ' there'],
            ],
            $record['parts'],
        );
    }

    public function test_an_owned_smiley_still_renders_after_an_admin_deactivates_it(): void
    {
        $this->own($this->smiley);
        $this->smiley->update(['active' => false]);

        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hi (akuma)']);
        $record = $this->app->make(ChatService::class)->record($message->fresh()->load('member'));

        $this->assertSame(['t' => 'img', 'v' => '/chat/pawns/default.svg'], $record['parts'][1]);
    }

    public function test_a_smiley_the_sender_does_not_own_stays_literal_text(): void
    {
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hi (akuma) there']);
        $record = $this->app->make(ChatService::class)->record($message->fresh()->load('member'));

        $this->assertSame([['t' => 'text', 'v' => 'hi (akuma) there']], $record['parts']);
    }

    public function test_an_equipped_name_colour_reaches_the_record_as_a_custom_property(): void
    {
        $colour = ChatCosmetic::create([
            'type' => CosmeticType::NameColor,
            'name' => 'Sunset',
            'slug' => 'sunset',
            'payload' => ['color' => '#ff8800'],
            'price' => 0,
            'active' => true,
        ]);
        $this->own($colour, equipped: true);

        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hello']);
        $record = $this->app->make(ChatService::class)->record($message->fresh()->load('member'));

        $this->assertSame(['--chat-name-color' => '#ff8800'], $record['style']);
    }

    public function test_an_unequipped_cosmetic_does_not_style_anything(): void
    {
        $colour = ChatCosmetic::create([
            'type' => CosmeticType::NameColor,
            'name' => 'Sunset',
            'slug' => 'sunset',
            'payload' => ['color' => '#ff8800'],
            'price' => 0,
            'active' => true,
        ]);
        $this->own($colour, equipped: false);

        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hello']);

        $this->assertSame([], $this->app->make(ChatService::class)->record($message->fresh()->load('member'))['style']);
    }

    public function test_a_body_that_looks_like_a_code_but_is_not_a_cosmetic_is_left_alone(): void
    {
        $parts = (new MessageRenderer)->parts('see (notacode) here', []);

        $this->assertSame([['t' => 'text', 'v' => 'see (notacode) here']], $parts);
    }

    private function cosmetic(CosmeticType $type, string $slug, array $payload): ChatCosmetic
    {
        return ChatCosmetic::create([
            'type' => $type,
            'name' => $slug,
            'slug' => $slug,
            'payload' => $payload,
            'price' => 0,
            'active' => true,
        ]);
    }

    /** @return array<string, array{string}> */
    public static function hostilePayloads(): array
    {
        return [
            'css injection' => ['red;background:url(//evil)'],
            'style close' => ['</style><script>alert(1)</script>'],
            'trailing newline' => ["#ff8800\n"],
        ];
    }

    #[DataProvider('hostilePayloads')]
    public function test_a_hostile_payload_in_the_database_renders_the_default_look_everywhere(string $color): void
    {
        $hostile = $this->cosmetic(CosmeticType::NameColor, 'evil', ['color' => $color]);
        $this->own($hostile, equipped: true);
        $this->member->forceFill(['email_verified_at' => now()])->save();

        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hello']);

        // Live payload.
        $record = $this->app->make(ChatService::class)->record($message->fresh()->load('member'));
        $this->assertSame([], $record['style']);

        // History handoff.
        $html = $this->withoutVite()->get('/chat')->assertOk()->getContent();
        $this->assertSame(1, preg_match('#id="chat-bootstrap">(.*?)</script>#s', $html, $m));
        $history = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR)['history'];
        $this->assertSame([], $history[0]['style']);
        $this->assertStringNotContainsString('evil', $m[1]);
    }

    public function test_a_valid_equipped_cosmetic_reaches_the_history_handoff(): void
    {
        $this->own($this->cosmetic(CosmeticType::NameGradient, 'fade', ['from' => '#ff0000', 'to' => '#0000ff', 'angle' => 90]), equipped: true);
        $this->own($this->cosmetic(CosmeticType::Glow, 'glowy', ['color' => '#00ff00', 'strength' => 2]), equipped: true);
        ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hello']);

        $html = $this->withoutVite()->get('/chat')->assertOk()->getContent();
        $this->assertSame(1, preg_match('#id="chat-bootstrap">(.*?)</script>#s', $html, $m));
        $history = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR)['history'];

        $this->assertSame([
            '--chat-name-gradient' => 'linear-gradient(90deg, #ff0000, #0000ff)',
            '--chat-glow' => '0 0 8px #00ff00',
        ], $history[0]['style']);
    }

    public function test_a_member_with_no_cosmetics_gets_empty_style_and_one_text_part(): void
    {
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'plain']);
        $record = $this->app->make(ChatService::class)->record($message->fresh()->load('member'));

        $this->assertSame([], $record['style']);
        $this->assertSame([['t' => 'text', 'v' => 'plain']], $record['parts']);
    }

    public function test_a_hostile_smiley_payload_stays_literal_text(): void
    {
        $bad = $this->cosmetic(CosmeticType::Smiley, 'badsmile', ['code' => '(x)', 'asset' => '../../.env']);
        $this->own($bad);
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hi (x)']);
        $record = $this->app->make(ChatService::class)->record($message->fresh()->load('member'));

        $this->assertSame([['t' => 'text', 'v' => 'hi (x)']], $record['parts']);
    }

    public function test_the_record_never_exposes_the_internal_member_id(): void
    {
        $this->own($this->smiley, equipped: true);
        $message = ChatMessage::create(['member_id' => $this->member->id, 'body' => 'hi (akuma)']);
        $record = $this->app->make(ChatService::class)->record($message->fresh()->load('member'));

        $this->assertArrayNotHasKey('member_id', $record);
        $this->assertSame($this->member->uuid, $record['uuid']);
        $this->assertStringNotContainsString('"id":'.$this->member->id.',', json_encode(['parts' => $record['parts'], 'style' => $record['style']]));
    }

    public function test_rebuilding_history_resolves_cosmetics_in_a_bounded_number_of_queries(): void
    {
        $colour = $this->cosmetic(CosmeticType::NameColor, 'sunset', ['color' => '#ff8800']);

        foreach (range(1, 6) as $i) {
            $m = Member::create(['name' => "M{$i}", 'email' => "m{$i}@example.test", 'password' => 'password123!']);
            ChatCosmeticMember::create([
                'member_id' => $m->id, 'cosmetic_id' => $colour->id,
                'acquired_at' => now(), 'source' => 'purchase', 'equipped' => true,
            ]);
            ChatMessage::create(['member_id' => $m->id, 'body' => 'a']);
            ChatMessage::create(['member_id' => $m->id, 'body' => 'b']);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $history = $this->app->make(ChatService::class)->history();
        $queries = count(DB::getQueryLog());

        $this->assertCount(12, $history);
        $this->assertSame(['--chat-name-color' => '#ff8800'], $history[0]['style']);
        // messages + members eager load + inventory + cosmetics eager load,
        // plus the cache write: independent of the number of speakers.
        $this->assertLessThanOrEqual(5, $queries);
    }
}
