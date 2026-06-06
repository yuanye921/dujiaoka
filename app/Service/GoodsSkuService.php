<?php

namespace App\Service;

use App\Exceptions\RuleValidationException;
use App\Models\BaseModel;
use App\Models\Carmis;
use App\Models\Goods;
use App\Models\GoodsSku;

class GoodsSkuService
{
    public function ensureDefaultSku(Goods $goods): GoodsSku
    {
        $sku = GoodsSku::query()
            ->where('goods_id', $goods->id)
            ->where('sku_code', GoodsSku::DEFAULT_SKU_CODE)
            ->first();

        if ($sku) {
            $this->fillMissingFromGoods($sku, $goods);
            return $sku;
        }

        $sku = new GoodsSku();
        $sku->goods_id = $goods->id;
        $sku->sku_name = '默认规格';
        $sku->sku_code = GoodsSku::DEFAULT_SKU_CODE;
        $sku->actual_price = $goods->actual_price;
        $sku->picture = $goods->picture;
        $sku->in_stock = $goods->in_stock;
        $sku->ord = 1;
        $sku->is_open = BaseModel::STATUS_OPEN;
        $sku->save();

        return $sku;
    }

    public function syncAfterGoodsSaved(Goods $goods): void
    {
        $skus = GoodsSku::query()
            ->where('goods_id', $goods->id)
            ->orderBy('ord', 'DESC')
            ->orderBy('id')
            ->get();

        if ($skus->isEmpty()) {
            $skus = collect([$this->ensureDefaultSku($goods)]);
        }

        $this->ensureOneDefaultCode($goods, $skus);

        $skus = GoodsSku::query()
            ->where('goods_id', $goods->id)
            ->orderBy('ord', 'DESC')
            ->orderBy('id')
            ->get();

        foreach ($skus as $sku) {
            $this->fillMissingFromGoods($sku, $goods);
        }

        $activeSkus = $skus->where('is_open', BaseModel::STATUS_OPEN);
        if ($activeSkus->isEmpty()) {
            $activeSkus = $skus;
        }

        $minPrice = $activeSkus->min('actual_price');
        $stock = (int) $activeSkus->sum('in_stock');

        Goods::query()->where('id', $goods->id)->update([
            'actual_price' => $minPrice === null ? $goods->actual_price : $minPrice,
            'in_stock' => $stock,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function resolveForGoods(Goods $goods, $skuID = null): GoodsSku
    {
        $query = GoodsSku::query()
            ->where('goods_id', $goods->id)
            ->where('is_open', BaseModel::STATUS_OPEN);

        if ($skuID) {
            $sku = (clone $query)->where('id', $skuID)->first();
            if (!$sku) {
                throw new RuleValidationException('请选择有效的商品规格');
            }
        } else {
            $sku = (clone $query)->where('sku_code', GoodsSku::DEFAULT_SKU_CODE)->first();
        }

        if (!$sku) {
            $sku = $this->ensureDefaultSku($goods);
        }

        if ((int) $sku->is_open !== BaseModel::STATUS_OPEN) {
            throw new RuleValidationException('该规格已下架');
        }

        return $sku;
    }

    public function availableStock(Goods $goods, GoodsSku $sku): int
    {
        if ((int) $goods->type === Goods::AUTOMATIC_DELIVERY) {
            return Carmis::query()
                ->where('goods_id', $goods->id)
                ->where('sku_id', $sku->id)
                ->where('status', Carmis::STATUS_UNSOLD)
                ->count();
        }

        return max(0, (int) $sku->in_stock);
    }

    public function options(): array
    {
        return GoodsSku::query()
            ->with('goods')
            ->orderBy('goods_id')
            ->orderBy('ord', 'DESC')
            ->get()
            ->mapWithKeys(function (GoodsSku $sku) {
                return [$sku->id => $sku->display_name];
            })
            ->toArray();
    }

    private function ensureOneDefaultCode(Goods $goods, $skus): void
    {
        $default = $skus->first(function (GoodsSku $sku) {
            return $sku->sku_code === GoodsSku::DEFAULT_SKU_CODE;
        });

        if ($default) {
            return;
        }

        $first = $skus->first();
        if (!$first) {
            return;
        }

        $first->sku_code = GoodsSku::DEFAULT_SKU_CODE;
        $first->save();
    }

    private function fillMissingFromGoods(GoodsSku $sku, Goods $goods): void
    {
        $changed = false;

        if (empty($sku->sku_name)) {
            $sku->sku_name = '默认规格';
            $changed = true;
        }

        if (empty($sku->sku_code)) {
            $sku->sku_code = GoodsSku::DEFAULT_SKU_CODE;
            $changed = true;
        }

        if ($sku->actual_price === null || $sku->actual_price === '') {
            $sku->actual_price = $goods->actual_price;
            $changed = true;
        }

        if (empty($sku->picture) && !empty($goods->picture)) {
            $sku->picture = $goods->picture;
            $changed = true;
        }

        if ($sku->in_stock === null || $sku->in_stock === '') {
            $sku->in_stock = $goods->in_stock;
            $changed = true;
        }

        if ($sku->ord === null || $sku->ord === '') {
            $sku->ord = 1;
            $changed = true;
        }

        if ($sku->is_open === null || $sku->is_open === '') {
            $sku->is_open = BaseModel::STATUS_OPEN;
            $changed = true;
        }

        if ($changed) {
            $sku->save();
        }
    }
}
