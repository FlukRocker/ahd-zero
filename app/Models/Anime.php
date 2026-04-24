<?php

namespace App\Models;

use Database\Factories\AnimeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @property int $cat_id
 * @property string $cat_title
 * @property string|null $cat_desc
 * @property string|null $cat_tag
 * @property string|null $cat_image
 * @property int $cat_type
 * @property \Illuminate\Support\Carbon|null $cat_update
 * @property mixed $cat_disable
 * @property string|null $title_english
 * @property string|null $title_japanese
 * @property string|null $title_synonyms
 * @property string|null $anime_type
 * @property int|null $episodes
 * @property string|null $anime_status
 * @property \Illuminate\Support\Carbon|null $aired_from
 * @property \Illuminate\Support\Carbon|null $aired_to
 * @property string|null $premiered_season
 * @property int|null $premiered_year
 * @property string|null $broadcast
 * @property string|null $source
 * @property string|null $duration
 * @property string|null $rating
 * @property int|null $mal_id
 * @property array<int, string>|null $opening_themes
 * @property array<int, string>|null $ending_themes
 * @property string|null $review_url
 * @property int|null $series_id
 * @property int|null $series_order
 * @property string|null $anime_slug
 * @property string|null $anime_tags
 * @property string|null $cat_banner
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read string|null $banner_original
 * @property-read string|null $banner_md
 * @property-read string|null $banner_th
 * @property-read string|null $cover_original
 * @property-read string|null $cover_md
 * @property-read string|null $cover_th
 */
class Anime extends Model
{
    /** @use HasFactory<AnimeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'yu_anime_catagory';

    protected $primaryKey = 'cat_id';

    public $timestamps = false;

    protected $fillable = [
        'cat_title',
        'cat_desc',
        'cat_tag',
        'cat_image',
        'cat_type',
        'cat_update',
        'cat_disable',
        'title_english',
        'title_japanese',
        'title_synonyms',
        'anime_type',
        'episodes',
        'anime_status',
        'aired_from',
        'aired_to',
        'premiered_season',
        'premiered_year',
        'broadcast',
        'source',
        'duration',
        'rating',
        'mal_id',
        'opening_themes',
        'ending_themes',
        'review_url',
        'series_id',
        'series_order',
        'anime_slug',
        'anime_tags',
        'cat_banner',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'cat_type' => 'integer',
            'cat_update' => 'datetime',
            'episodes' => 'integer',
            'aired_from' => 'date',
            'aired_to' => 'date',
            'premiered_year' => 'integer',
            'mal_id' => 'integer',
            'opening_themes' => 'array',
            'ending_themes' => 'array',
            'series_id' => 'integer',
            'series_order' => 'integer',
        ];
    }

    public function getBannerOriginalAttribute(): ?string
    {
        return $this->cat_banner;
    }

    public function getBannerMdAttribute(): ?string
    {
        return app(\App\Services\ImageVariantService::class)->getVariant($this->cat_banner, 'md');
    }

    public function getBannerThAttribute(): ?string
    {
        return app(\App\Services\ImageVariantService::class)->getVariant($this->cat_banner, 'th');
    }

    public function getCoverOriginalAttribute(): ?string
    {
        return $this->cat_image;
    }

    public function getCoverMdAttribute(): ?string
    {
        return app(\App\Services\ImageVariantService::class)->getVariant($this->cat_image, 'md');
    }

    public function getCoverThAttribute(): ?string
    {
        return app(\App\Services\ImageVariantService::class)->getVariant($this->cat_image, 'th');
    }

    /**
     * @return HasMany<Episode, $this>
     */
    public function episodeList(): HasMany
    {
        return $this->hasMany(Episode::class, 'catagory_id', 'cat_id');
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'taggables', 'taggable_id', 'tag_id')
            ->where('tags.type', 'genre')
            ->wherePivot('taggable_type', self::class);
    }

    /**
     * @return BelongsToMany<Studio, $this>
     */
    public function studios(): BelongsToMany
    {
        return $this->belongsToMany(Studio::class, 'anime_studio', 'anime_id', 'studio_id')
            ->wherePivot('role', 'studio')
            ->withPivot('role');
    }

    /**
     * @return BelongsToMany<Studio, $this>
     */
    public function producers(): BelongsToMany
    {
        return $this->belongsToMany(Studio::class, 'anime_studio', 'anime_id', 'studio_id')
            ->wherePivot('role', 'producer')
            ->withPivot('role');
    }

    /**
     * @return BelongsToMany<Studio, $this>
     */
    public function licensors(): BelongsToMany
    {
        return $this->belongsToMany(Studio::class, 'anime_studio', 'anime_id', 'studio_id')
            ->wherePivot('role', 'licensor')
            ->withPivot('role');
    }

    /**
     * @return BelongsToMany<Character, $this>
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'anime_character', 'anime_id', 'character_id')
            ->withPivot(['voice_actor_id', 'character_role']);
    }

    /**
     * @return BelongsToMany<Staff, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'anime_staff', 'anime_id', 'staff_id')
            ->withPivot('position');
    }

    /**
     * @return HasMany<AnimeRelation, $this>
     */
    public function relations(): HasMany
    {
        return $this->hasMany(AnimeRelation::class, 'anime_id', 'cat_id');
    }
}
