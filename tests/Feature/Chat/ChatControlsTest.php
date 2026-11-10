<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\MessageSent;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Member;
use App\Support\Chat\ChatSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BacksUpSiteSettings;
use Tests\TestCase;

class ChatControlsTest extends TestCase
{
    use BacksUpSiteSettings;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([MessageSent::class]);
        $this->backUpSiteSettings();
    }

    protected function tearDown(): void
    {
        $this->restoreSiteSettings();
        parent::tearDown();
    }

    private function member(string $name, string $rank = 'member'): Member
    {
        $m = Member::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'password123!']);

        if ($rank !== 'member') {
            ChatMember::create(['member_id' => $m->id, 'rank' => $rank, 'balance' => 0]);
        }

        return $m;
    }

    private function say(Member $m, string $body)
    {
        return $this->actingAs($m, 'member')->postJson('/chat/messages', ['body' => $body]);
    }

    public function test_a_locked_room_refuses_members_but_not_mods(): void
    {
        ChatSettings::setRoom(ChatRoom::lobby()->id, true, 0);

        $this->say($this->member('Aki'), 'hi')->assertForbidden();
        $this->say($this->member('Mo', 'mod'), 'hi')->assertCreated();
        $this->assertSame(1, ChatMessage::query()->count());
    }

    public function test_locking_one_room_does_not_lock_another(): void
    {
        $other = ChatRoom::create(['slug' => 'anime', 'name' => 'Anime', 'active' => true, 'sort' => 1]);
        ChatSettings::setRoom($other->id, true, 0);

        $this->say($this->member('Aki'), 'hi')->assertCreated();
    }

    public function test_slow_mode_allows_one_message_per_window_for_members_only(): void
    {
        ChatSettings::setRoom(ChatRoom::lobby()->id, false, 30);
        $aki = $this->member('Aki');

        $this->say($aki, 'one')->assertCreated();
        $this->say($aki, 'two')->assertStatus(429);
        $this->assertSame(1, ChatMessage::query()->count());

        $mod = $this->member('Mo', 'mod');
        $this->say($mod, 'a')->assertCreated();
        $this->say($mod, 'b')->assertCreated();
    }

    public function test_a_banned_word_is_refused_case_insensitively_even_split_by_tags_and_does_not_burn_slow_mode(): void
    {
        ChatSettings::setWords("Spamword\nxx\n");
        ChatSettings::setRoom(ChatRoom::lobby()->id, false, 30);
        $aki = $this->member('Aki');

        $this->say($aki, 'this is SPAMWORD here')->assertStatus(422);
        $this->say($aki, 'spam[b]word[/b]')->assertStatus(422);
        // The refusals did not consume the slow-mode slot.
        $this->say($aki, 'clean message')->assertCreated();
    }

    public function test_the_word_list_is_cleaned_deduplicated_and_length_limited(): void
    {
        ChatSettings::setWords("  Bad \nbad\nx\n".str_repeat('y', 41)."\n\n  ok  ");

        $this->assertSame(['bad', 'ok'], ChatSettings::words());
    }

    public function test_announcement_reaches_the_page_bootstrap_and_the_mini_json(): void
    {
        ChatSettings::setAnnouncement("Maintenance\ntonight");

        $this->assertSame('Maintenance tonight', ChatSettings::announcement());
        $this->getJson('/chat/mini')->assertOk()->assertJsonPath('announcement', 'Maintenance tonight');
        $this->assertStringContainsString('"announcement":"Maintenance tonight"', (string) $this->get('/chat')->getContent());

        ChatSettings::setAnnouncement('');
        $this->getJson('/chat/mini')->assertJsonPath('announcement', null);
    }
}
