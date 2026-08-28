<?php

namespace Tests\Feature;

use App\Models\Carmis;
use App\Models\GameLicense;
use App\Models\Order;
use App\Service\GameLicenseService;
use App\Service\ManualPlusIssueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ManualPlusIssueServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'licenses.code_pepper' => 'manual-issue-test-pepper',
            'licenses.plus_sku_code' => 'GAME_PLUS',
        ]);
        $this->createTables();
        $this->service = new ManualPlusIssueService(app(GameLicenseService::class));
    }

    public function test_unsold_plus_code_is_issued_with_a_completed_zero_value_order(): void
    {
        $carmisId = $this->seedCarmis('YYJP-ABCD-EFGH-IJKL', Carmis::STATUS_UNSOLD, 'GAME_PLUS');

        $license = $this->service->issue($carmisId, ' Buyer@Example.com ', [
            'admin_id' => 19,
            'ip' => '10.0.0.8',
        ]);

        $order = Order::query()->findOrFail($license->order_id);
        $this->assertSame('buyer@example.com', $order->email);
        $this->assertSame(Order::STATUS_COMPLETED, (int) $order->status);
        $this->assertSame(Order::MANUAL_PROCESSING, (int) $order->type);
        $this->assertSame(0.0, (float) $order->actual_price);
        $this->assertSame('YYJP-ABCD-EFGH-IJKL', $order->info);
        $this->assertSame('10.0.0.8', $order->buy_ip);
        $this->assertSame('MANUAL-PLUS:19', $order->trade_no);
        $this->assertSame(Carmis::STATUS_SOLD, (int) Carmis::query()->findOrFail($carmisId)->status);
        $this->assertDatabaseHas('game_licenses', [
            'id' => $license->id,
            'carmis_id' => $carmisId,
            'order_id' => $order->id,
            'status' => GameLicense::STATUS_ACTIVE,
        ]);
    }

    public function test_invalid_email_does_not_change_inventory_or_create_records(): void
    {
        $carmisId = $this->seedCarmis('YYJP-ABCD-EFGH-IJKL', Carmis::STATUS_UNSOLD, 'GAME_PLUS');

        try {
            $this->service->issue($carmisId, 'not-an-email');
            $this->fail('Invalid email should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertSame(Carmis::STATUS_UNSOLD, (int) Carmis::query()->findOrFail($carmisId)->status);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, GameLicense::query()->count());
    }

    public function test_already_sold_code_cannot_be_issued_again(): void
    {
        $carmisId = $this->seedCarmis('YYJP-ABCD-EFGH-IJKL', Carmis::STATUS_SOLD, 'GAME_PLUS');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('只有未售出的 Plus 卡密可以手动发放。');

        $this->service->issue($carmisId, 'buyer@example.com');
    }

    public function test_non_plus_code_rolls_back_order_and_inventory_changes(): void
    {
        $carmisId = $this->seedCarmis('YYJP-ABCD-EFGH-IJKL', Carmis::STATUS_UNSOLD, 'DEFAULT');

        try {
            $this->service->issue($carmisId, 'buyer@example.com');
            $this->fail('Non-Plus inventory should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('这张卡密不属于解锁码 Plus。', $exception->getMessage());
        }

        $this->assertSame(Carmis::STATUS_UNSOLD, (int) Carmis::query()->findOrFail($carmisId)->status);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, GameLicense::query()->count());
    }

    public function test_license_registration_failure_rolls_back_order_and_sold_status(): void
    {
        $carmisId = $this->seedCarmis('BROKEN-PLUS-CODE', Carmis::STATUS_UNSOLD, 'GAME_PLUS');

        try {
            $this->service->issue($carmisId, 'buyer@example.com');
            $this->fail('Malformed Plus inventory should fail during license registration.');
        } catch (RuntimeException $exception) {
            $this->assertSame('A Plus inventory code has an invalid format.', $exception->getMessage());
        }

        $this->assertSame(Carmis::STATUS_UNSOLD, (int) Carmis::query()->findOrFail($carmisId)->status);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, GameLicense::query()->count());
    }

    private function seedCarmis(string $code, int $status, string $skuCode): int
    {
        $now = now();
        $skuId = DB::table('goods_skus')->insertGetId([
            'goods_id' => 27,
            'sku_name' => $skuCode === 'GAME_PLUS' ? '解锁码plus' : '普通解锁码',
            'sku_code' => $skuCode,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('carmis')->insertGetId([
            'goods_id' => 27,
            'sku_id' => $skuId,
            'carmi' => $code,
            'is_loop' => 0,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createTables(): void
    {
        foreach (['game_licenses', 'carmis', 'orders', 'goods_skus'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('goods_skus', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('goods_id');
            $table->string('sku_name');
            $table->string('sku_code');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_sn')->unique();
            $table->integer('goods_id');
            $table->integer('sku_id');
            $table->integer('coupon_id')->default(0);
            $table->string('title');
            $table->integer('type');
            $table->decimal('goods_price', 10, 2)->default(0);
            $table->integer('buy_amount')->default(1);
            $table->decimal('coupon_discount_price', 10, 2)->default(0);
            $table->decimal('wholesale_discount_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->decimal('actual_price', 10, 2)->default(0);
            $table->string('search_pwd')->default('');
            $table->string('email');
            $table->text('info')->nullable();
            $table->integer('pay_id')->nullable();
            $table->string('buy_ip');
            $table->string('trade_no')->default('');
            $table->integer('status');
            $table->integer('coupon_ret_back')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('carmis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('goods_id');
            $table->integer('sku_id');
            $table->string('carmi');
            $table->integer('is_loop')->default(0);
            $table->integer('status');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('game_licenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('carmis_id')->unique();
            $table->bigInteger('order_id');
            $table->integer('sku_id');
            $table->char('code_hash', 64)->unique();
            $table->string('game_id')->nullable();
            $table->char('device_token_hash', 64)->nullable();
            $table->char('install_id_hash', 64)->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_legacy')->default(false);
            $table->boolean('requires_email_verification')->default(false);
            $table->unsignedInteger('binding_version')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('recovery_override_until')->nullable();
            $table->timestamps();
        });
    }
}
