<?php

namespace App\View\Composers;

use App\Support\AdsNavbar;
use App\Support\SiteSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalComposer
{
    public function __construct(private Request $request) {}

    public function compose(View $view): void
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $member = $this->request->user('member');

        $view->with([
            'siteName' => config('app.name'),
            'appUrl' => config('app.url'),
            'authUser' => $this->request->user(),
            'memberAuth' => $member ? [
                'id' => $member->uuid,
                'name' => $member->name,
                'email' => $member->email,
                'avatar' => $member->avatar,
            ] : null,
            'playerConfig' => [
                'adsEmbedUrl' => config('services.akuma_player.ads_embed_url'),
            ],
            'siteConfig' => [
                'registrationEnabled' => SiteSettings::registrationEnabled(),
                'turnstileSiteKey' => config('services.turnstile.site_key'),
            ],
            'navbarAds' => AdsNavbar::all(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
        ]);
    }
}
