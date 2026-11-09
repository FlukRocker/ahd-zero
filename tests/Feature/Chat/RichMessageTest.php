<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\MessageSent;
use App\Models\ChatMessage;
use App\Models\Member;
use App\Support\Chat\Bbcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RichMessageTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake([MessageSent::class]);

        $this->member = Member::create([
            'name' => 'Rin', 'email' => 'rin@example.test', 'password' => 'password123!', 'email_verified_at' => now(),
        ]);
    }

    private function send(array $data)
    {
        return $this->actingAs($this->member, 'member')->postJson('/chat/messages', $data);
    }

    /** @return array<string, array{0: string}> */
    public static function links(): array
    {
        return [
            'https' => ['go https://evil.test/x'],
            'www' => ['see www.evil.test'],
            'bare tld' => ['evil.com is great'],
            'fullwidth' => ['ｈｔｔｐｓ：／／ａ．ｃｏ'],
            'zero width split' => ["ev\u{200B}il.c\u{200D}om"],
            'javascript' => ['javascript:alert(1)'],
            'bbcode split 1' => ['evil[b][/b].com'],
            'bbcode split 2' => ['https[s][/s]://evil.test'],
            'bbcode split 3' => ['ev[color=#fff][/color]il.com'],
            'internal host too' => ['https://animehdzero.net/anime/1'],
        ];
    }

    #[DataProvider('links')]
    public function test_links_are_blocked(string $body): void
    {
        $this->send(['body' => $body])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_ordinary_text_is_not_mistaken_for_a_link(): void
    {
        foreach (['ep.12 สนุกมาก', 'ok.thanks', '3.5 stars', '<script>alert(1)</script>'] as $body) {
            $this->send(['body' => $body])->assertCreated();
        }
    }

    public function test_html_stays_literal_text(): void
    {
        $record = $this->send(['body' => '<img src=x onerror=alert(1)>'])->assertCreated()->json();

        $this->assertSame([['t' => 'text', 'v' => '<img src=x onerror=alert(1)>']], $record['parts']);
    }

    public function test_bbcode_becomes_flat_segments(): void
    {
        $parts = Bbcode::parse('a [b]B[color=#F00]R[/color][/b] [u]x[/u]');

        $this->assertSame(['t' => 'text', 'v' => 'B', 'b' => true], $parts[1]);
        $this->assertSame(['t' => 'text', 'v' => 'R', 'b' => true, 'c' => '#f00'], $parts[2]);
        $this->assertSame(['t' => 'text', 'v' => 'x', 'u' => true], $parts[4]);
    }

    public function test_bad_bbcode_stays_literal(): void
    {
        foreach (['[b]open', '[color=red]x[/color]', '[color=#12]x[/color]', '[url=x]x[/url]', '[color=#fff;x]y'] as $text) {
            $parts = Bbcode::parse($text);

            $this->assertSame($text, implode('', array_column($parts, 'v')), $text);

            foreach ($parts as $part) {
                $this->assertSame(['t', 'v'], array_keys($part), $text);
            }
        }
    }

    public function test_an_https_image_url_becomes_an_image_segment(): void
    {
        $record = $this->send(['body' => 'look [img]https://i.example.test/a/cat.PNG?x=1[/img] nice'])
            ->assertCreated()->json();

        $this->assertSame(
            [['t' => 'text', 'v' => 'look '], ['t' => 'image', 'v' => 'https://i.example.test/a/cat.PNG?x=1'], ['t' => 'text', 'v' => ' nice']],
            $record['parts'],
        );
    }

    public function test_an_image_inside_a_spoiler_carries_the_flag(): void
    {
        $parts = Bbcode::parse('[spoiler][img]https://i.example.test/a.jpg[/img][/spoiler]');

        $this->assertSame([['t' => 'image', 'v' => 'https://i.example.test/a.jpg', 'sp' => true]], $parts);
    }

    public function test_an_image_inside_bold_does_not_split_the_formatting(): void
    {
        $parts = Bbcode::parse('[b]x [img]https://i.example.test/a.gif[/img] y[/b]');

        $this->assertSame(['t' => 'text', 'v' => 'x ', 'b' => true], $parts[0]);
        $this->assertSame('image', $parts[1]['t']);
        $this->assertSame(['t' => 'text', 'v' => ' y', 'b' => true], $parts[2]);
    }

    /** @return array<string, array{0: string}> */
    public static function badImages(): array
    {
        return [
            'http' => ['[img]http://i.example.test/a.png[/img]'],
            'javascript' => ['[img]javascript:alert(1)[/img]'],
            'data' => ['[img]data:image/png;base64,AAAA[/img]'],
            'no extension' => ['[img]https://i.example.test/a[/img]'],
            'wrong extension' => ['[img]https://i.example.test/a.svg[/img]'],
            'extension only in query' => ['[img]https://i.example.test/a?x=.png[/img]'],
            'ip host' => ['[img]https://127.0.0.1/a.png[/img]'],
            'localhost' => ['[img]https://localhost/a.png[/img]'],
            'credentials' => ['[img]https://good.example.test@evil.test/a.png[/img]'],
            'port' => ['[img]https://i.example.test:8443/a.png[/img]'],
            'quote' => ['[img]https://i.example.test/a".png[/img]'],
            'space' => ['[img]https://i.example.test/a b.png[/img]'],
            'angle' => ['[img]https://i.example.test/a<b>.png[/img]'],
            'nested tag' => ['[img]https://i.example.test/[b]a[/b].png[/img]'],
            'unclosed' => ['[img]https://i.example.test/a.png'],
            'too long' => ['[img]https://i.example.test/'.'a'.str_repeat('a', 400).'.png[/img]'],
        ];
    }

    #[DataProvider('badImages')]
    public function test_bad_image_embeds_are_rejected(string $body): void
    {
        $this->send(['body' => $body])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_a_malformed_embed_that_is_not_a_link_posts_as_plain_text(): void
    {
        foreach (['[img]//i.example.test/a.png[/img]', '[img]a1b2c3d4e5f60718293a4b5c6d7e8f90.webp[/img]'] as $body) {
            $record = $this->send(['body' => $body])->assertCreated()->json();

            $this->assertSame([['t' => 'text', 'v' => $body]], $record['parts']);
        }
    }

    public function test_a_link_outside_the_embed_is_still_blocked(): void
    {
        $this->send(['body' => 'https://evil.test [img]https://i.example.test/a.png[/img]'])->assertStatus(422);
        $this->send(['body' => 'evil[img]https://i.example.test/a.png[/img].com'])->assertStatus(422);
    }

    public function test_at_most_three_images_per_message(): void
    {
        $one = '[img]https://i.example.test/a.png[/img]';

        $this->send(['body' => $one.$one.$one])->assertCreated();
        $this->send(['body' => $one.$one.$one.$one])->assertStatus(422)->assertJsonValidationErrors('body');
    }
}
