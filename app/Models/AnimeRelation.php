<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $anime_id
 * @property int|null $related_anime_id
 * @property int|null $related_mal_id
 * @property string|null $related_title
 * @property string|null $relation_type
 */
class AnimeRelation extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'anime_relations';

    public $timestamps = false;

    protected $fillable = [
        'anime_id',
        'related_anime_id',
        'related_mal_id',
        'related_title',
        'relation_type',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'anime_id' => 'integer',
            'related_anime_id' => 'integer',
            'related_mal_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Anime, $this>
     */
    public function relatedAnime(): BelongsTo
    {
        return $this->belongsTo(Anime::class, 'related_anime_id', 'cat_id');
    }
}
