<?php

namespace App\Service;

use App\Models\Carmis;
use App\Models\GameLicense;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ManualPlusIssueService
{
    private $licenses;

    public function __construct(GameLicenseService $licenses)
    {
        $this->licenses = $licenses;
    }

    public function issue(int $carmisId, string $email, array $metadata = []): GameLicense
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 200) {
            throw ValidationException::withMessages([
                'email' => ['请输入有效的购买者邮箱。'],
            ]);
        }

        return DB::transaction(function () use ($carmisId, $email, $metadata) {
            $carmis = Carmis::query()
                ->with('sku')
                ->whereKey($carmisId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $carmis->status !== Carmis::STATUS_UNSOLD) {
                throw new RuntimeException('只有未售出的 Plus 卡密可以手动发放。');
            }
            if ((int) $carmis->is_loop !== 0) {
                throw new RuntimeException('循环卡密不能作为 Plus 授权手动发放。');
            }

            $sku = $carmis->sku;
            if (!$sku || strtoupper(trim((string) $sku->sku_code)) !== strtoupper((string) config('licenses.plus_sku_code', 'GAME_PLUS'))) {
                throw new RuntimeException('这张卡密不属于解锁码 Plus。');
            }

            $order = new Order();
            $order->order_sn = 'MANUAL-PLUS-' . strtoupper(Str::random(18));
            $order->goods_id = $carmis->goods_id;
            $order->sku_id = $carmis->sku_id;
            $order->coupon_id = 0;
            $order->title = '后台手动发放 - ' . $sku->sku_name . ' x 1';
            $order->type = Order::MANUAL_PROCESSING;
            $order->goods_price = 0;
            $order->buy_amount = 1;
            $order->coupon_discount_price = 0;
            $order->wholesale_discount_price = 0;
            $order->total_price = 0;
            $order->actual_price = 0;
            $order->search_pwd = '';
            $order->email = $email;
            $order->info = $carmis->carmi;
            $order->pay_id = null;
            $order->buy_ip = trim((string) ($metadata['ip'] ?? '')) ?: '127.0.0.1';
            $order->trade_no = 'MANUAL-PLUS:' . (string) ($metadata['admin_id'] ?? 'unknown');
            $order->status = Order::STATUS_COMPLETED;
            $order->coupon_ret_back = Order::COUPON_BACK_WAIT;
            $order->save();

            $carmis->status = Carmis::STATUS_SOLD;
            $carmis->save();

            if ($this->licenses->registerSoldCarmis([$carmis->id], $order, false) !== 1) {
                throw new RuntimeException('Plus 授权登记失败，卡密没有被发放。');
            }

            return GameLicense::query()->where('carmis_id', $carmis->id)->firstOrFail();
        }, 3);
    }
}
