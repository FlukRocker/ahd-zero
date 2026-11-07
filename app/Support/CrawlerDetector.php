<?php

namespace App\Support;

/**
 * Tells crawlers, SEO tools, link previewers and scripts apart from people,
 * so page-view analytics (and the trending lists built on it) count visitors.
 *
 * Deliberately narrow, matched on the user-agent only:
 * - "<name>bot/", "<name>bot;" etc. catch Googlebot, bingbot, AhrefsBot,
 *   SemrushBot, MJ12bot, DotBot, PetalBot, YandexBot, Applebot, ...
 *   A trailing space is not enough, so phones such as "CUBOT X30" pass.
 * - A list of named tools that don't say "bot".
 * - An empty user agent.
 *
 * Copied unchanged into ahd-v2, anime-hana, kurokami-v2 and neko-miku;
 * keep the copies identical.
 */
class CrawlerDetector
{
    private const PATTERN = '~'
        . '(?:bot|crawler|spider|slurp)(?:[/;:+)-]|\.html|$)'      // Googlebot/2.1, Googlebot-Image, coccocbot-web, "...; 360Spider"
        . '|\b(?:bot|crawler|spider|crawl)\b'                       // "... crawl ...", "(compatible; bot)"
        . '|ahrefs|semrush|mj12|dataforseo|serpstat|seokicks|blexbot|barkrowler|bytespider|petalbot'
        . '|yandex|baiduspider|sogou|exabot|seznam|qwantify|duckduckgo-favicons'
        . '|adsbot|mediapartners-google|google-inspectiontool|googleother|storebot-google|apis-google|feedfetcher|google-read-aloud|google-safety'
        . '|facebookexternalhit|facebookcatalog|meta-externalagent|meta-externalfetcher|twitterbot|discordbot|telegrambot|whatsapp|slackbot|linkedinbot|skypeuripreview|embedly|redditbot|pinterest'
        . '|gptbot|chatgpt-user|oai-searchbot|claudebot|claude-web|anthropic-ai|perplexity|ccbot|amazonbot|applebot|bingpreview|cohere-ai|diffbot|imagesift|omgili|timpibot'
        . '|headlesschrome|phantomjs|puppeteer|playwright|selenium|lighthouse|chrome-lighthouse|pagespeed|gtmetrix'
        . '|pingdom|uptimerobot|statuscake|site24x7|newrelicpinger|datadog|monitor'
        . '|python-requests|python-urllib|aiohttp|httpx|go-http-client|okhttp|java/|curl/|wget/|libwww|scrapy|httpclient|node-fetch|axios/|guzzlehttp|postmanruntime|insomnia'
        . '|scraper|scanner|paloaltonetworks|domaindrift|builtwith|cms-checker|stackconflict|google-apps-script|wordpress/'
        . '~i';

    public static function isCrawler(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent === '' || preg_match(self::PATTERN, $userAgent) === 1;
    }
}
