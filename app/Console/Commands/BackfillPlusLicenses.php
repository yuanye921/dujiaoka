<?php

namespace App\Console\Commands;

use App\Models\Carmis;
use App\Models\GameLicense;
use App\Models\Order;
use App\Service\GameLicenseService;
use Illuminate\Console\Command;

class BackfillPlusLicenses extends Command
{
    protected $signature = 'licenses:backfill-plus {--dry-run : Inspect and report without writing records}';
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
        $stats = ['orders' => 0, 'matched' => 0, 'existing' => 0, 'malformed' => 0, 'missing' => 0, 'duplicate' => 0];
        $plusSkuCode = (string) config('licenses.plus_sku_code', 'GAME_PLUS');

        $query = Order::query()
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('sku', function ($q) use ($plusSkuCode) {
                $q->where('sku_code', $plusSkuCode);
            })
            ->with('sku')
            ->orderBy('id');

        $query->chunkById(200, function ($orders) use (&$stats, $dryRun) {
            foreach ($orders as $order) {
                $stats['orders']++;
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
                        $this->licenses->registerSoldCarmis([$item->id], $order, true);
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
}
