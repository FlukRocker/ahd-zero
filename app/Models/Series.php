<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $title
 * @property string|null $title_japanese
 * @property string|null $description
 * @property string|null $image
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Series extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'series';

    protected $fillable = [
        'title',
        'title_japanese',
        'description',
        'image',
    ];

    /**
     * @return HasMany<Anime, $this>
     */
    public function anime(): HasMany
    {
        return $this->hasMany(Anime::class, 'series_id')->orderBy('series_order')->orderBy('cat_type');
    }
}
