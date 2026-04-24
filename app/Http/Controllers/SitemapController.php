<?php

namespace App\Http\Controllers;

use App\Models\Anime;
use App\Models\Episode;
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
                        $lastmod = $anime->cat_update?->toIso8601String() ?? '';
                        echo '<url>';
                        echo "<loc>{$loc}</loc>";
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
            echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            $chunkSize = 1000;
            $processed = 0;

            while ($processed < $limit) {
                $take = min($chunkSize, $limit - $processed);
                $rows = Episode::query()
                    ->whereNull('deleted_at')
                    ->select('list_id', 'catagory_id', 'adddate')
                    ->orderBy('list_id')
                    ->offset($offset + $processed)
                    ->limit($take)
                    ->get();

                if ($rows->isEmpty()) {
                    break;
                }

                foreach ($rows as $ep) {
                    $loc = "{$baseUrl}/anime/{$ep->catagory_id}/episode/{$ep->list_id}";
                    $lastmod = $ep->adddate?->toIso8601String() ?? '';
                    echo '<url>';
                    echo "<loc>{$loc}</loc>";
                    if ($lastmod !== '') {
                        echo "<lastmod>{$lastmod}</lastmod>";
                    }
                    echo '<changefreq>monthly</changefreq>';
                    echo '<priority>0.5</priority>';
                    echo '</url>';
                }

                $processed += $rows->count();
                flush();
                unset($rows);
            }

            echo '</urlset>';
        }, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
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
        $content .= "Disallow: /member/login\n";
        $content .= "Disallow: /member/register\n";
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
