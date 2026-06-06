<?php

namespace App\Service;

use App\Exceptions\RuleValidationException;
use App\Models\BaseModel;
use App\Models\Carmis;
use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\Order;

class GoodsSkuService
{
    public function ensureDefaultSku(Goods $goods): GoodsSku
    {
        $realSku = $this->firstRealSku($goods);
        if ($realSku) {
            return $realSku;
        }

        $sku = GoodsSku::query()
            ->withTrashed()
            ->where('goods_id', $goods->id)
            ->where('sku_code', GoodsSku::DEFAULT_SKU_CODE)
            ->first();

        if ($sku) {
            if ($sku->trashed()) {
                $sku->restore();
            }
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

        foreach ($skus as $sku) {
            $this->fillMissingFromGoods($sku, $goods);
        }

        $this->closeEmptyDefaultSkuWhenRealSkusExist($goods, $skus);

        $skus = GoodsSku::query()
            ->where('goods_id', $goods->id)
            ->orderBy('ord', 'DESC')
            ->orderBy('id')
            ->get();

        $activeSkus = $this->payableSkus($skus->where('is_open', BaseModel::STATUS_OPEN));
        if ($activeSkus->isEmpty()) {
            $activeSkus = $this->payableSkus($skus);
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
            $sku = $this->payableSkus(
                (clone $query)->orderBy('ord', 'DESC')->orderBy('id')->get()
            )->first();
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
            ->groupBy('goods_id')
            ->flatMap(function ($skus) {
                return $this->payableSkus($skus);
            })
            ->mapWithKeys(function (GoodsSku $sku) {
                return [$sku->id => $sku->display_name];
            })
            ->toArray();
    }

    public function visibleSkus($skus)
    {
        $skus = collect($skus)->values();
        $realSkus = $skus->filter(function ($sku) {
            return !$this->isDefaultLikeSku($sku);
        })->values();

        return $realSkus->isNotEmpty() ? $realSkus : $skus;
    }

    public function payableSkus($skus)
    {
        $visibleSkus = $this->visibleSkus($skus);
        $pricedSkus = $visibleSkus->filter(function ($sku) {
            return (float) data_get($sku, 'actual_price', 0) > 0;
        })->values();

        return $pricedSkus->isNotEmpty() ? $pricedSkus : $visibleSkus;
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

    private function closeEmptyDefaultSkuWhenRealSkusExist(Goods $goods, $skus): void
    {
        $defaultSku = collect($skus)->first(function ($sku) {
            return $this->isDefaultLikeSku($sku);
        });

        if (!$defaultSku) {
            return;
        }

        $hasRealSku = collect($skus)->contains(function ($sku) use ($defaultSku) {
            return $sku->id !== $defaultSku->id
                && !$this->isDefaultLikeSku($sku);
        });

        if (!$hasRealSku) {
            return;
        }

        $hasCards = Carmis::query()->where('sku_id', $defaultSku->id)->exists();
        $hasOrders = Order::query()->where('sku_id', $defaultSku->id)->exists();

        if (!$hasCards && !$hasOrders) {
            $defaultSku->delete();
            return;
        }

        $defaultSku->is_open = BaseModel::STATUS_CLOSE;
        $defaultSku->save();
    }

    private function firstRealSku(Goods $goods): ?GoodsSku
    {
        return GoodsSku::query()
            ->where('goods_id', $goods->id)
            ->orderBy('ord', 'DESC')
            ->orderBy('id')
            ->get()
            ->first(function ($sku) {
                return !$this->isDefaultLikeSku($sku);
            });
    }

    private function isDefaultLikeSku($sku): bool
    {
        $code = strtoupper((string) data_get($sku, 'sku_code'));
        $name = trim((string) data_get($sku, 'sku_name'));
        $price = (float) data_get($sku, 'actual_price', 0);

        if ($code === GoodsSku::DEFAULT_SKU_CODE) {
            return true;
        }

        if ($name === '默认规格') {
            return true;
        }

        return $price <= 0 && ($name === '' || strpos($name, '默认') !== false);
    }
}
