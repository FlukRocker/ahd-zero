<?php

namespace Tests\Unit;

use App\Support\CrawlerDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * User agents taken from the production page_views sample (2026-11-07).
 * The same test lives in ahd-v2, anime-hana and neko-miku (Pest in kurokami-v2).
 */
class CrawlerDetectorTest extends TestCase
{
    public static function crawlers(): array
    {
        return [
            ['Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'],
            ['Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)'],
            ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm) Chrome/116.0.1938.76 Safari/537.36'],
            ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
            ['Googlebot-Image/1.0'],
            ['Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)'],
            ['meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)'],
            ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'],
            ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0; 360Spider'],
            ['Mozilla/5.0 (compatible; coccocbot-web/1.0; +http://help.coccoc.com/searchengine)'],
            ['Mozilla/5.0 (compatible; animehdzero-scraper/1.0)'],
            ['curl/8.5.0'],
            ['python-requests/2.31.0'],
            [''],
            [null],
        ];
    }

    public static function people(): array
    {
        return [
            ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'],
            ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36'],
            ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1'],
            ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36'],
            ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0'],
            // Phones whose model name contains "bot"
            ['Mozilla/5.0 (Linux; Android 10; CUBOT X30 Build/QP1A.190711.020) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'],
            ['Mozilla/5.0 (Linux; Android 7.1.2; M_bot_tab_71) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.117 Safari/537.36'],
        ];
    }

    #[DataProvider('crawlers')]
    public function test_crawlers_are_detected(?string $userAgent): void
    {
        $this->assertTrue(CrawlerDetector::isCrawler($userAgent));
    }

    #[DataProvider('people')]
    public function test_browsers_are_not_crawlers(string $userAgent): void
    {
        $this->assertFalse(CrawlerDetector::isCrawler($userAgent));
    }
}
