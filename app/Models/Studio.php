<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $name
 * @property string|null $name_japanese
 * @property int|null $mal_id
 */
class Studio extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'studios';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'name_japanese',
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
