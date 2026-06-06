<?php

namespace App\Console\Commands;

use App\Models\Goods;
use App\Service\GoodsSkuService;
use Illuminate\Console\Command;

class SyncGoodsSkuSummary extends Command
{
    protected $signature = 'dujiaoka:sync-sku-summary';

    protected $description = 'Sync goods display price and stock from payable SKUs.';

    public function handle(GoodsSkuService $goodsSkuService): int
    {
        $count = 0;

        Goods::query()->chunkById(100, function ($goods) use ($goodsSkuService, &$count) {
            foreach ($goods as $item) {
                $goodsSkuService->syncAfterGoodsSaved($item);
                $count++;
            }
        });

        $this->info("Synced {$count} goods.");

        return 0;
    }
}
