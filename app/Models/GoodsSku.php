<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GoodsSku extends BaseModel
{
    use SoftDeletes;

    const DEFAULT_SKU_CODE = 'DEFAULT';

    protected $table = 'goods_skus';

    protected static function boot()
    {
        parent::boot();

        static::saving(function (GoodsSku $sku) {
            if (empty($sku->sku_name)) {
                $sku->sku_name = '默认规格';
            }

            if (empty($sku->sku_code)) {
                $sku->sku_code = self::makeUniqueCode($sku->goods_id);
            }

            if ($sku->actual_price === null || $sku->actual_price === '') {
                $sku->actual_price = 0;
            }

            if ($sku->in_stock === null || $sku->in_stock === '') {
                $sku->in_stock = 0;
            }

            if ($sku->ord === null || $sku->ord === '') {
                $sku->ord = 1;
            }

            if ($sku->is_open === null || $sku->is_open === '') {
                $sku->is_open = self::STATUS_OPEN;
            }
        });
    }

    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id');
    }

    public function carmis()
    {
        return $this->hasMany(Carmis::class, 'sku_id');
    }

    public static function getIsOpenMap()
    {
        return [
            self::STATUS_OPEN => '启用',
            self::STATUS_CLOSE => '禁用',
        ];
    }

    public function getDisplayNameAttribute()
    {
        $goodsName = $this->goods ? $this->goods->gd_name : ('商品#' . $this->goods_id);
        return $goodsName . ' - ' . $this->sku_name;
    }

    private static function makeUniqueCode($goodsID): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = 'SKU-' . strtoupper(Str::random(8));

            if (!$goodsID || !self::query()->where('goods_id', $goodsID)->where('sku_code', $code)->exists()) {
                return $code;
            }
        }

        return 'SKU-' . strtoupper(Str::random(12));
    }
}
