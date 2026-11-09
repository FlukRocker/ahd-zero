<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\MessageSent;
use App\Models\ChatMessage;
use App\Models\Member;
use App\Services\Chat\ChatImage;
use App\Support\Chat\Bbcode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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
        Storage::fake('local');
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

    public function test_typed_img_tag_is_rejected(): void
    {
        $this->send(['body' => '[img]'.str_repeat('a', 32).'.webp[/img]'])->assertStatus(422);
    }

    public function test_image_is_reencoded_stored_and_served(): void
    {
        $file = UploadedFile::fake()->image('cat.png', 800, 600);

        $record = $this->send(['body' => 'look [b]this[/b]', 'image' => $file])->assertCreated()->json();

        $upload = collect($record['parts'])->firstWhere('t', 'upload');
        $this->assertNotNull($upload);
        $this->assertMatchesRegularExpression('~^/chat-uploads/[a-f0-9]{32}\.(webp|jpg)$~', $upload['v']);
        Storage::disk('local')->assertExists('chat-uploads/'.basename($upload['v']));

        $this->get($upload['v'])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control');
        $this->assertStringContainsString('private', $this->get($upload['v'])->headers->get('Cache-Control'));
    }

    public function test_image_only_message_is_allowed(): void
    {
        $this->send(['body' => '', 'image' => UploadedFile::fake()->image('a.jpg', 50, 50)])->assertCreated();
    }

    public function test_non_images_are_rejected(): void
    {
        $php = UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;');
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->send(['body' => 'hi', 'image' => $php])->assertStatus(422);
        $this->send(['body' => 'hi', 'image' => $svg])->assertStatus(422);
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_oversized_dimensions_are_rejected(): void
    {
        $this->send(['body' => 'big', 'image' => UploadedFile::fake()->image('big.png', ChatImage::MAX_SIDE + 1, 10)])
            ->assertStatus(422);
    }

    public function test_deleting_a_message_removes_its_image(): void
    {
        $record = $this->send(['body' => 'x', 'image' => UploadedFile::fake()->image('a.png', 40, 40)])->assertCreated()->json();
        $file = basename(collect($record['parts'])->firstWhere('t', 'upload')['v']);

        ChatMessage::query()->findOrFail($record['id'])->delete();

        Storage::disk('local')->assertMissing('chat-uploads/'.$file);
        $this->get('/chat-uploads/'.$file)->assertNotFound();
    }

    public function test_upload_route_rejects_other_names(): void
    {
        $this->get('/chat-uploads/..%2F.env')->assertNotFound();
        $this->get('/chat-uploads/evil.php')->assertNotFound();
    }

    public function test_image_uploads_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->send(['body' => 'x', 'image' => UploadedFile::fake()->image('a.png', 20, 20)])->assertCreated();
        }

        $this->send(['body' => 'x', 'image' => UploadedFile::fake()->image('a.png', 20, 20)])->assertStatus(429);
    }
}
