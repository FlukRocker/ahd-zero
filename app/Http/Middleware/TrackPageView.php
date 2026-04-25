<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function config;
use function hash;
use function mb_substr;
use function now;
use function parse_url;

class TrackPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->method() !== 'GET' || $response->getStatusCode() !== 200) {
            return $response;
        }

        // Mongo down / missing ext / network blip must never take down a
        // public page — analytics is best-effort, swallow + drop.
        try {
            $this->track($request);
        } catch (Throwable) {
            // ignore
        }

        return $response;
    }

    private function track(Request $request): void
    {
        $route = $request->route();
        if ($route === null) {
            return;
        }

        $routeName = $route->getName();
        $pageType = $this->resolvePageType($routeName);
        if ($pageType === null) {
            return;
        }

        $params = $route->parameters();
        $pageId = $this->resolvePageId($routeName, $params);
        $sessionId = $request->session()->getId();
        $site = (string) config('app.site_key');

        // Deduplicate: same session + same page within 30 minutes (scoped
        // per site so identical paths on kurokami vs ahd dedupe independently).
        $exists = PageView::query()
            ->where('site', $site)
            ->where('session_id', $sessionId)
            ->where('page_type', $pageType)
            ->where('page_id', $pageId)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($exists) {
            return;
        }

        $referrer = $request->header('referer');
        $referrerDomain = null;
        if ($referrer !== null && $referrer !== '') {
            $parsed = parse_url($referrer, PHP_URL_HOST);
            $referrerDomain = $parsed !== false && $parsed !== null ? $parsed : null;
        }

        $member = Auth::guard('member')->user();
        $admin = Auth::guard('web')->user();

        PageView::create([
            'site' => $site,
            'page_type' => $pageType,
            'page_id' => $pageId,
            'page_title' => $this->resolveTitle($routeName, $pageId, $params),
            'user_id' => $member->uuid ?? ($admin !== null ? ($admin->uuid ?? (string) $admin->id) : null),
            'session_id' => $sessionId,
            'referrer' => $referrer !== null ? mb_substr($referrer, 0, 500) : null,
            'referrer_domain' => $referrerDomain,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);
    }

    private function resolvePageType(?string $routeName): ?string
    {
        return match ($routeName) {
            'home' => 'home',
            'anime.show' => 'anime',
            'anime.episode' => 'episode',
            'category' => 'category',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolvePageId(?string $routeName, array $params): ?int
    {
        return match ($routeName) {
            'anime.show' => isset($params['id']) ? (int) $params['id'] : null,
            'anime.episode' => isset($params['listId']) ? (int) $params['listId'] : null,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveTitle(?string $routeName, ?int $pageId, array $params): string
    {
        return match ($routeName) {
            'home' => 'หน้าแรก',
            'anime.show' => $pageId !== null
                ? (string) (DB::table('yu_anime_catagory')->where('cat_id', $pageId)->value('cat_title') ?? 'Anime #'.$pageId)
                : 'Anime',
            'anime.episode' => $pageId !== null
                ? (string) (DB::table('yu_anime_list')->where('list_id', $pageId)->value('list_title') ?? 'Episode #'.$pageId)
                : 'Episode',
            'category' => match ($params['type'] ?? null) {
                '1' => 'ซับไทย',
                '2' => 'พากย์ไทย',
                '3' => 'เดอะมูฟวี่',
                default => 'หมวดหมู่',
            },
            default => 'Unknown',
        };
    }
}
