<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends BaseModel
{
    use SoftDeletes;

    protected $table = 'banners';

    protected $fillable = [
        'title',
        'subtitle',
        'button_text',
        'image',
        'link',
        'ord',
        'is_open',
    ];

    public static function getIsOpenMap()
    {
        return [
            self::STATUS_OPEN => '启用',
            self::STATUS_CLOSE => '禁用',
        ];
    }
}
