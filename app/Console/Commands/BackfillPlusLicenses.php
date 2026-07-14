<?php

namespace App\Console\Commands;

use App\Models\Carmis;
use App\Models\GameLicense;
use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\Order;
use App\Service\GameLicenseService;
use Illuminate\Console\Command;

class BackfillPlusLicenses extends Command
{
    protected $signature = 'licenses:backfill-plus
        {--dry-run : Inspect and report without writing records}
        {--list-candidates : List former standalone products whose completed orders contain YYJP codes}
        {--legacy-goods-id=* : Explicit former standalone Plus product ID; may be repeated}';
    protected $description = 'Build Plus license ownership records from completed historical orders.';

    private $licenses;

    public function __construct(GameLicenseService $licenses)
    {
        parent::__construct();
        $this->licenses = $licenses;
    }

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $legacyGoodsIds = $this->legacyGoodsIds();
        $stats = ['orders' => 0, 'legacy_orders' => 0, 'matched' => 0, 'existing' => 0, 'malformed' => 0, 'missing' => 0, 'duplicate' => 0];
        $plusSkuCode = (string) config('licenses.plus_sku_code', 'GAME_PLUS');

        if ((bool) $this->option('list-candidates')) {
            return $this->listCandidates($plusSkuCode);
        }

        if ($legacyGoodsIds) {
            $this->info('Including former standalone Plus product IDs: ' . implode(', ', $legacyGoodsIds));
        }

        $query = Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->where(function ($q) use ($plusSkuCode, $legacyGoodsIds) {
                $q->whereHas('sku', function ($sku) use ($plusSkuCode) {
                    $sku->where('sku_code', $plusSkuCode);
                });
                if ($legacyGoodsIds) {
                    $q->orWhereIn('goods_id', $legacyGoodsIds);
                }
            })
            ->with('sku')
            ->orderBy('id');

        $query->chunkById(200, function ($orders) use (&$stats, $dryRun, $legacyGoodsIds, $plusSkuCode) {
            foreach ($orders as $order) {
                $stats['orders']++;
                if (in_array((int) $order->goods_id, $legacyGoodsIds, true)
                    && (!$order->sku || strtoupper((string) $order->sku->sku_code) !== strtoupper($plusSkuCode))) {
                    $stats['legacy_orders']++;
                }
                $seen = [];
                $hadNonEmptyLine = false;
                $lines = preg_split('/\R+/', (string) $order->info);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $hadNonEmptyLine = true;

                    $normalized = $this->licenses->normalizeCode($line);
                    if (!$this->licenses->isValidCode($normalized)) {
                        $stats['malformed']++;
                        $this->warn("Order {$order->order_sn}: malformed code line {$line}");
                        continue;
                    }
                    if (isset($seen[$normalized])) {
                        $stats['duplicate']++;
                        $this->warn("Order {$order->order_sn}: duplicate code line {$normalized}");
                        continue;
                    }
                    $seen[$normalized] = true;

                    $carmis = Carmis::withTrashed()
                        ->where('sku_id', $order->sku_id)
                        ->where('carmi', $normalized)
                        ->get();
                    if ($carmis->count() !== 1) {
                        $stats[$carmis->count() === 0 ? 'missing' : 'duplicate']++;
                        $this->warn("Order {$order->order_sn}: inventory match count {$carmis->count()} for {$normalized}");
                        continue;
                    }

                    $item = $carmis->first();
                    $existing = GameLicense::query()
                        ->where('carmis_id', $item->id)
                        ->orWhere('code_hash', $this->licenses->codeHash($normalized))
                        ->get();
                    if ($existing->isNotEmpty()) {
                        $exact = $existing->count() === 1
                            && (int) $existing->first()->carmis_id === (int) $item->id
                            && (int) $existing->first()->order_id === (int) $order->id
                            && hash_equals((string) $existing->first()->code_hash, $this->licenses->codeHash($normalized));
                        if ($exact) {
                            $stats['existing']++;
                        } else {
                            $stats['duplicate']++;
                            $this->warn("Order {$order->order_sn}: license already belongs to another inventory item or order for {$normalized}");
                        }
                        continue;
                    }

                    $stats['matched']++;
                    if (!$dryRun) {
                        $registered = $this->licenses->registerSoldCarmis([$item->id], $order, true, $legacyGoodsIds);
                        if ($registered !== 1) {
                            $stats['matched']--;
                            $stats['missing']++;
                            $this->warn("Order {$order->order_sn}: selected inventory is not from a recognized Plus product");
                        }
                    }
                }
                if (!$hadNonEmptyLine) {
                    $stats['missing']++;
                    $this->warn("Order {$order->order_sn}: order info does not contain a card code");
                }
            }
        });

        $this->table(['Metric', 'Count'], collect($stats)->map(function ($value, $key) {
            return [$key, $value];
        })->values()->all());
        $this->info($dryRun ? 'Dry run completed; no records were written.' : 'Backfill completed.');
        return ($stats['missing'] + $stats['duplicate'] + $stats['malformed']) > 0 ? 2 : 0;
    }

    private function legacyGoodsIds(): array
    {
        $values = array_merge(
            (array) config('licenses.legacy_plus_goods_ids', []),
            (array) $this->option('legacy-goods-id')
        );
        $ids = [];
        foreach ($values as $value) {
            foreach (preg_split('/[\s,]+/', trim((string) $value)) as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
        ksort($ids);
        return array_values($ids);
    }

    private function listCandidates(string $plusSkuCode): int
    {
        $groups = Order::query()
            ->select(['goods_id', 'sku_id'])
            ->selectRaw('COUNT(*) AS order_count')
            ->where('status', Order::STATUS_COMPLETED)
            ->where('info', 'like', '%YYJP-%')
            ->whereDoesntHave('sku', function ($sku) use ($plusSkuCode) {
                $sku->where('sku_code', $plusSkuCode);
            })
            ->groupBy('goods_id', 'sku_id')
            ->orderBy('goods_id')
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No former standalone product candidates were found.');
            return 0;
        }

        $goods = Goods::withTrashed()
            ->whereIn('id', $groups->pluck('goods_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');
        $skus = GoodsSku::withTrashed()
            ->whereIn('id', $groups->pluck('sku_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $rows = $groups->map(function ($group) use ($goods, $skus) {
            $product = $goods->get($group->goods_id);
            $sku = $skus->get($group->sku_id);
            return [
                (int) $group->goods_id,
                $product ? $product->gd_name : '(deleted or missing)',
                (int) $group->sku_id,
                $sku ? $sku->sku_name : '(deleted or missing)',
                $sku ? $sku->sku_code : '',
                (int) $group->order_count,
            ];
        })->all();

        $this->table(
            ['Goods ID', 'Product', 'SKU ID', 'SKU', 'SKU code', 'Completed YYJP orders'],
            $rows
        );
        $this->warn('This is a read-only candidate list. Confirm the former standalone Plus product by name before using its Goods ID.');
        return 0;
    }
}
