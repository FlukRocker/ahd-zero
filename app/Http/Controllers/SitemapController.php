<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Episode;
use App\Models\EpisodePlayerUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function abort_if;
use function ceil;
use function config;
use function flush;
use function min;
use function response;

class SitemapController extends Controller
{
    private const EPISODES_PER_SITEMAP = 45000;

    public function index(): Response
    {
        $content = Cache::remember('sitemap:index', 3600, function (): string {
            $baseUrl = config('app.url');

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            $xml .= "<sitemap><loc>{$baseUrl}/sitemap-pages.xml</loc></sitemap>";
            $xml .= "<sitemap><loc>{$baseUrl}/sitemap-anime.xml</loc></sitemap>";

            $episodeCount = Episode::query()->whereNull('deleted_at')->count();
            $episodePages = (int) ceil($episodeCount / self::EPISODES_PER_SITEMAP);
            for ($p = 1; $p <= $episodePages; $p++) {
                $xml .= "<sitemap><loc>{$baseUrl}/sitemap-episodes-{$p}.xml</loc></sitemap>";
            }

            $xml .= '</sitemapindex>';

            return $xml;
        });

        return response($content, 200, ['Content-Type' => 'application/xml']);
    }

    public function pages(): Response
    {
        $content = Cache::remember('sitemap:pages', 3600, function (): string {
            $baseUrl = config('app.url');

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            $xml .= "<url><loc>{$baseUrl}</loc><changefreq>daily</changefreq><priority>1.0</priority></url>";
            $xml .= "<url><loc>{$baseUrl}/category/1</loc><changefreq>daily</changefreq><priority>0.8</priority></url>";
            $xml .= "<url><loc>{$baseUrl}/category/2</loc><changefreq>daily</changefreq><priority>0.8</priority></url>";
            $xml .= "<url><loc>{$baseUrl}/category/3</loc><changefreq>daily</changefreq><priority>0.8</priority></url>";
            $xml .= "<url><loc>{$baseUrl}/studios</loc><changefreq>weekly</changefreq><priority>0.5</priority></url>";
            $xml .= "<url><loc>{$baseUrl}/voice-actors</loc><changefreq>weekly</changefreq><priority>0.5</priority></url>";
            $xml .= "<url><loc>{$baseUrl}/staff</loc><changefreq>weekly</changefreq><priority>0.5</priority></url>";

            $xml .= '</urlset>';

            return $xml;
        });

        return response($content, 200, ['Content-Type' => 'application/xml']);
    }

    public function anime(): StreamedResponse
    {
        $baseUrl = config('app.url');

        return new StreamedResponse(function () use ($baseUrl): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            Anime::query()
                ->select('cat_id', 'cat_update', 'anime_slug')
                ->orderByDesc('cat_update')
                ->chunkById(1000, function ($animes) use ($baseUrl): void {
                    foreach ($animes as $anime) {
                        $loc = "{$baseUrl}/anime/{$anime->cat_id}";
                        $lastmod = self::lastmod($anime->cat_update);
                        echo '<url>';
                        echo '<loc>'.self::xml($loc).'</loc>';
                        if ($lastmod !== '') {
                            echo "<lastmod>{$lastmod}</lastmod>";
                        }
                        echo '<changefreq>weekly</changefreq>';
                        echo '<priority>0.7</priority>';
                        echo '</url>';
                    }
                    flush();
                }, 'cat_id');

            echo '</urlset>';
        }, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function episodes(int $page): StreamedResponse
    {
        abort_if($page < 1, 404);

        $baseUrl = config('app.url');
        $offset = ($page - 1) * self::EPISODES_PER_SITEMAP;
        $limit = self::EPISODES_PER_SITEMAP;

        return new StreamedResponse(function () use ($baseUrl, $offset, $limit): void {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
                .' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">';

            // Find where this page starts once, then walk forward by primary
            // key. OFFSET per chunk made every chunk scan and discard all the
            // rows before it — 2.4s a chunk by the middle of a page, which is
            // what used to run the stream into the execution-time limit.
            $cursor = Episode::query()
                ->whereNull('deleted_at')
                ->orderBy('list_id')
                ->offset($offset)
                ->limit(1)
                ->value('list_id');

            if ($cursor === null) {
                echo '</urlset>';

                return;
            }

            $chunkSize = 1000;
            $processed = 0;
            // An anime has many episodes, so the same cat_id repeats across
            // chunks. Held for the whole stream, each series is fetched once.
            $series = [];

            while ($processed < $limit) {
                $take = min($chunkSize, $limit - $processed);
                $rows = Episode::query()
                    ->whereNull('deleted_at')
                    ->select('list_id', 'catagory_id', 'adddate', 'list_title')
                    ->where('list_id', '>=', $cursor)
                    ->orderBy('list_id')
                    ->limit($take)
                    ->get();

                if ($rows->isEmpty()) {
                    break;
                }

                // One query per chunk for the resolved player URLs, plus one for
                // whichever series this chunk introduced. Per-row lookups would
                // make this 90k queries.
                $watchUrls = $this->watchUrlsFor($rows->pluck('list_id')->all());
                $this->loadSeriesMeta($rows->pluck('catagory_id')->unique()->all(), $series);

                foreach ($rows as $ep) {
                    // Some imported rows carry a series title in catagory_id
                    // instead of an id. The route matches digits only, so those
                    // URLs 404 — and a title containing "&" made the whole
                    // 45k-URL file fail to parse as XML.
                    if (! ctype_digit((string) $ep->catagory_id)) {
                        continue;
                    }

                    $loc = "{$baseUrl}/anime/{$ep->catagory_id}/episode/{$ep->list_id}";
                    $lastmod = self::lastmod($ep->adddate);
                    echo '<url>';
                    echo '<loc>'.self::xml($loc).'</loc>';
                    if ($lastmod !== '') {
                        echo "<lastmod>{$lastmod}</lastmod>";
                    }
                    echo '<changefreq>monthly</changefreq>';
                    echo '<priority>0.5</priority>';
                    echo $this->videoTag($ep, $watchUrls, $series);
                    echo '</url>';
                }

                $processed += $rows->count();
                $cursor = $rows->last()->list_id + 1;
                flush();
                unset($rows, $watchUrls);
            }

            echo '</urlset>';
        }, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Resolved player URLs, keyed by list_id. Only episodes that actually have
     * a video come back — the rest get no <video:video>, which keeps the
     * sitemap consistent with the noindex those pages already render.
     *
     * @param  array<int,int>  $listIds
     * @return array<int,string>
     */
    private function watchUrlsFor(array $listIds): array
    {
        return EpisodePlayerUrl::query()
            ->whereIn('list_id', $listIds)
            ->whereNotNull('watch_url')
            ->pluck('watch_url', 'list_id')
            ->all();
    }

    /**
     * Fill $series (keyed by cat_id) with any of $catIds it doesn't hold yet.
     * The cache is what makes this one query per chunk rather than per episode.
     *
     * @param  array<int,int>  $catIds
     * @param  array<int,array{title:string,image:?string,desc:?string}>  $series
     */
    private function loadSeriesMeta(array $catIds, array &$series): void
    {
        $missing = array_values(array_diff($catIds, array_keys($series)));

        if ($missing === []) {
            return;
        }

        foreach (Anime::query()->whereIn('cat_id', $missing)->get(['cat_id', 'cat_title', 'cat_image', 'cat_desc']) as $anime) {
            $series[$anime->cat_id] = [
                'title' => (string) $anime->cat_title,
                'image' => $anime->cat_image,
                'desc' => $anime->cat_desc,
            ];
        }
    }

    /**
     * Google requires thumbnail_loc, title, description and a player_loc or
     * content_loc. If any of them is missing the whole tag is dropped: a
     * partial <video:video> is a sitemap error, not a partial win.
     *
     * @param  array<int,string>  $watchUrls
     * @param  array<int,array{title:string,image:?string,desc:?string}>  $series
     */
    private function videoTag(Episode $ep, array $watchUrls, array $series): string
    {
        $watchUrl = $watchUrls[$ep->list_id] ?? null;
        $meta = $series[$ep->catagory_id] ?? null;

        if ($watchUrl === null || $meta === null) {
            return '';
        }

        $thumbnail = $meta['image'];
        if ($thumbnail === null || $thumbnail === '') {
            return '';
        }

        $title = trim($meta['title'].' — '.$ep->list_title);
        // Google rejects an empty description, so fall back to the title.
        $description = trim(strip_tags((string) $meta['desc'])) ?: $title;

        return '<video:video>'
            .'<video:thumbnail_loc>'.self::xml($thumbnail).'</video:thumbnail_loc>'
            .'<video:title>'.self::xml(self::clamp($title, 100)).'</video:title>'
            .'<video:description>'.self::xml(self::clamp($description, 2048)).'</video:description>'
            .'<video:player_loc>'.self::xml($watchUrl).'</video:player_loc>'
            .'<video:family_friendly>yes</video:family_friendly>'
            .'<video:live>no</video:live>'
            .'</video:video>';
    }

    /**
     * The imported tables carry zero dates ("0000-00-00"), which Carbon hands
     * back as year -0001. Emitting that produces a <lastmod> Google rejects, so
     * anything implausible is dropped — the tag is optional, a broken one isn't.
     */
    private static function lastmod(?\Illuminate\Support\Carbon $date): string
    {
        if ($date === null || $date->year < 1990 || $date->isFuture()) {
            return '';
        }

        return $date->toIso8601String();
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Multibyte-safe so a cut never lands mid-character and breaks the XML —
     * these titles and descriptions are Thai.
     */
    private static function clamp(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    /**
     * https://llmstxt.org — a plain-language map of the site for LLM crawlers,
     * which robots.txt already allows. Deliberately a short index of hubs, not
     * a catalogue dump: the sitemaps carry the 95k episode URLs.
     */
    public function llms(): Response
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $name = config('app.name', 'Anime HD Zero');

        $content = "# {$name}\n\n";
        $content .= "> ดูอนิเมะออนไลน์ ซับไทย พากย์ไทย และเดอะมูฟวี่ คุณภาพ HD อัปเดตทุกวัน\n\n";
        $content .= "{$name} is a Thai-language anime streaming catalogue. Every series has a detail\n";
        $content .= "page listing its episodes, and each episode has its own watch page.\n\n";

        $content .= "## หมวดหมู่ (Categories)\n\n";
        foreach ([1 => 'อนิเมะซับไทย', 2 => 'อนิเมะพากย์ไทย', 3 => 'อนิเมะเดอะมูฟวี่'] as $id => $label) {
            $content .= "- [{$label}]({$baseUrl}/category/{$id})\n";
        }

        $content .= "\n## ไดเรกทอรี (Directories)\n\n";
        foreach (['studios' => 'สตูดิโอ', 'voice-actors' => 'นักพากย์', 'staff' => 'ทีมงาน'] as $path => $label) {
            $content .= "- [{$label}]({$baseUrl}/{$path})\n";
        }

        $content .= "\n## โครงสร้าง URL (URL patterns)\n\n";
        $content .= "- Series: `{$baseUrl}/anime/{id}`\n";
        $content .= "- Episode: `{$baseUrl}/anime/{id}/episode/{episodeId}`\n";

        $content .= "\n## Optional\n\n";
        $content .= "- [Sitemap index]({$baseUrl}/sitemap.xml)\n";

        return response($content, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function robots(): Response
    {
        $baseUrl = config('app.url');

        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /dashboard\n";
        $content .= "Disallow: /dashboard/*\n";
        $content .= "Disallow: /settings\n";
        $content .= "Disallow: /settings/*\n";
        $content .= "Disallow: /login\n";
        $content .= "Disallow: /two-factor-challenge\n";
        // Every /member route is gated, so none of it is crawlable — and the
        // ones that aren't login/register answer a guest with a 302 to login,
        // which is a wasted hop rather than a page.
        $content .= "Disallow: /member/\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /search/results\n";
        $content .= "Disallow: /*?page=\n";
        $content .= "\n";
        foreach (['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-Web', 'anthropic-ai', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'cohere-ai', 'Bytespider'] as $bot) {
            $content .= "User-agent: {$bot}\n";
            $content .= "Allow: /\n\n";
        }
        $content .= "Sitemap: {$baseUrl}/sitemap.xml\n";

        return response($content, 200, ['Content-Type' => 'text/plain']);
    }
}
