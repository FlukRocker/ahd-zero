<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $name_th
 * @property int|null $order_column
 */
class Tag extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'tags';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'name_th',
        'order_column',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'order_column' => 'integer',
        ];
    }
}
