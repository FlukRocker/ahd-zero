<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $list_id
 * @property int $catagory_id
 * @property string $list_title
 * @property string $uuid
 * @property string|null $file_src
 * @property string|null $list_url
 * @property \Illuminate\Support\Carbon|null $adddate
 */
class Episode extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'yu_anime_list';

    protected $primaryKey = 'list_id';

    public $timestamps = false;

    protected $fillable = [
        'catagory_id',
        'list_title',
        'uuid',
        'file_src',
        'list_url',
    ];

    protected $casts = [
        'adddate' => 'datetime',
    ];

    /**
     * @return BelongsTo<Anime, $this>
     */
    public function anime(): BelongsTo
    {
        return $this->belongsTo(Anime::class, 'catagory_id', 'cat_id');
    }
}
