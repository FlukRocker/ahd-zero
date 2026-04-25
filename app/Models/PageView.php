<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * @property string $_id
 * @property string $site
 * @property string $page_type
 * @property int|null $page_id
 * @property string|null $page_title
 * @property string|null $user_id
 * @property string $session_id
 * @property string|null $referrer
 * @property string|null $referrer_domain
 * @property string|null $user_agent
 * @property string|null $ip_hash
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class PageView extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'page_views';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'site',
        'page_type',
        'page_id',
        'page_title',
        'user_id',
        'session_id',
        'referrer',
        'referrer_domain',
        'user_agent',
        'ip_hash',
    ];
}
