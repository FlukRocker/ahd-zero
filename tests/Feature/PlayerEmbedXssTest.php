<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * public/player/embed.php decodes ?link= and frames it after the pre-roll
 * ads. Unchecked, a javascript: or data: link ran script on this site's
 * origin for anyone who opened a crafted URL. Only an https player URL may
 * reach the page, and it must not be able to break out of the script.
 */
class PlayerEmbedXssTest extends TestCase
{
    private function render(mixed $link): string
    {
        $_GET = $link === null ? [] : ['link' => $link];
        ob_start();
        try {
            include base_path('public/player/embed.php');
        } finally {
            $html = (string) ob_get_clean();
            $_GET = [];
        }

        return $html;
    }

    public function test_an_https_player_url_is_framed_with_autoplay(): void
    {
        $html = $this->render(base64_encode('https://player.example/watch/one?x=1'));

        $this->assertStringContainsString('jwplayer("player")', $html);
        $this->assertStringContainsString(json_encode('https://player.example/watch/one?x=1&autoplay=1', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), $html);
    }

    /** @return array<string, array{mixed}> */
    public static function badLinks(): array
    {
        return [
            'javascript' => [base64_encode('javascript:alert(document.domain)')],
            'data' => [base64_encode('data:text/html,<script>alert(1)</script>')],
            'plain http' => [base64_encode('http://player.example/x')],
            'no scheme' => [base64_encode('//evil.example/x')],
            'not base64' => ['%%%'],
            'missing' => [null],
            'array' => [['x']],
        ];
    }

    #[DataProvider('badLinks')]
    public function test_anything_but_an_https_url_never_reaches_the_page(mixed $link): void
    {
        $html = $this->render($link);

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:text/html', $html);
        $this->assertStringNotContainsString('jwplayer("player")', $html, 'no player is set up for a rejected link');
    }

    public function test_a_hostile_https_url_cannot_break_out_of_the_script(): void
    {
        $html = $this->render(base64_encode('https://player.example/x?q="+alert(1)+"</script><script>alert(2)</script>'));

        $this->assertStringNotContainsString('<script>alert(2)', $html);
        $this->assertStringNotContainsString('"+alert(1)+"', $html);
    }
}
