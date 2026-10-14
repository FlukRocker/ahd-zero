<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resolved akuma-stream watch URL for one episode.
 *
 * Resolving means an HTTP call to akuma-stream per episode, so the answer is
 * persisted here instead of being re-derived on every page view and every
 * sitemap build. `App\Console\Commands\BackfillPlayerUrls` keeps it current.
 *
 * @property int $list_id
 * @property string|null $watch_url
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property int $attempts
 */
class EpisodePlayerUrl extends Model
{
    protected $table = 'episode_player_urls';

    protected $primaryKey = 'list_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'list_id',
        'watch_url',
        'resolved_at',
        'attempts',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * @return BelongsTo<Episode, $this>
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'list_id', 'list_id');
    }

    /**
     * A stored watch_url, made safe to hand to an iframe.
     *
     * The column holds an absolute URL, which bakes the akuma-stream hostname
     * into every row. When that host moved (control. → app.) all ~48k rows
     * went on pointing at a domain that no longer resolves, and only the sweep
     * would have rewritten them — a sweep that is itself a cron away from not
     * running. So re-base an akuma-stream path onto the configured host on the
     * way out and let config stay the one place the player's address lives.
     *
     * Anything that is not an http(s) URL is dropped: rows written before
     * PlayerService started rejecting non-URLs still hold a dash or the
     * episode title, and null sends the caller down the live-resolve path
     * instead of putting that string in a src attribute. A URL on a host that
     * is not akuma-stream is a direct link to someone else's player and is
     * returned untouched.
     */
    public static function rebase(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $parts = parse_url($stored);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! preg_match('#^https?$#i', $parts['scheme'])) {
            return null;
        }

        if (! str_ends_with(strtolower($parts['host']), 'akuma-stream.com')) {
            return $stored;
        }

        $base = rtrim((string) config('services.akuma_stream.url', 'https://app.akuma-stream.com'), '/');

        return $base.($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
