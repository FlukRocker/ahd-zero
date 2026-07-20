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
        $view->with($this->payload());
    }

    private function payload(): array
    {
        if ($this->request->attributes->has('global_view_data')) {
            return $this->request->attributes->get('global_view_data');
        }

        $data = $this->build();
        $this->request->attributes->set('global_view_data', $data);

        return $data;
    }

    private function build(): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $member = $this->request->user('member');

        try {
            $registrationEnabled = SiteSettings::registrationEnabled();
        } catch (\Throwable) {
            $registrationEnabled = false;
        }

        try {
            $navbarAds = AdsNavbar::all();
        } catch (\Throwable) {
            $navbarAds = [];
        }

        return [
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
                'registrationEnabled' => $registrationEnabled,
                'turnstileSiteKey' => config('services.turnstile.site_key'),
            ],
            'navbarAds' => $navbarAds,
            'quote' => ['message' => trim($message), 'author' => trim($author)],
        ];
    }
}
