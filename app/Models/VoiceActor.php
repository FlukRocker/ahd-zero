<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $name
 * @property string|null $name_japanese
 * @property string|null $image_url
 * @property string|null $language
 * @property int|null $mal_id
 */
class VoiceActor extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'voice_actors';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'name_japanese',
        'image_url',
        'language',
        'mal_id',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'mal_id' => 'integer',
        ];
    }
}
