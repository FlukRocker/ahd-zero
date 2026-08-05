<?php

namespace App\View\Composers;

use App\Support\SidebarData;
use Illuminate\View\View;

/**
 * Bound to the sidebar component alone, not to '*'. Admin and Inertia views
 * never render that component, so they never pay for these queries.
 */
class SidebarComposer
{
    public function __construct(private SidebarData $data) {}

    public function compose(View $view): void
    {
        $popular = $this->data->popular();

        $view->with([
            'sidebarGenres' => $this->data->genres(),
            'sidebarPopular' => $popular,
            // Mongo being down degrades every window to empty. Without this the
            // component would print a heading over nothing on every page.
            'sidebarHasPopular' => $this->data->hasPopular($popular),
        ]);
    }
}
