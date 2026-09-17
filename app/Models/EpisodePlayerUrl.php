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
}
