<?php

namespace Tests\Feature;

use App\Jobs\SendLicenseRecoveryCode;
use App\Models\GameLicense;
use App\Models\Order;
use App\Service\GameLicenseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GameLicenseServiceTest extends TestCase
{
    private $licenses;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'licenses.enabled' => true,
            'licenses.plus_sku_code' => 'GAME_PLUS',
            'licenses.code_pepper' => 'test-code-pepper',
            'licenses.token_pepper' => 'test-token-pepper',
            'licenses.otp_pepper' => 'test-otp-pepper',
            'licenses.privacy_pepper' => 'test-privacy-pepper',
            'licenses.games' => [
                'magic_world' => '魔法世界',
                'streamer_simulator' => '主播模拟器',
                'hougong_fenghualu' => '后宫·风华录',
            ],
        ]);
        $this->createTables();
        $this->licenses = app(GameLicenseService::class);
    }

    public function test_first_claim_is_one_game_and_one_current_browser(): void
    {
        $this->seedSoldPlusCode('YYJP-ABCD-EFGH-IJKL');

        $claim = $this->licenses->claim('YYJP-ABCD-EFGH-IJKL', 'magic_world', 'install-browser-a');
        $this->assertTrue($claim['ok']);
        $this->assertArrayHasKey('device_token', $claim);
        $this->assertNotSame($claim['device_token'], GameLicense::query()->first()->device_token_hash);

        $verify = $this->licenses->verify($claim['device_token'], 'magic_world', 'install-browser-a');
        $this->assertTrue($verify['ok']);
        $this->assertDatabaseHas('license_binding_events', ['event_type' => 'verified']);

        $sameBrowser = $this->licenses->claim('YYJP-ABCD-EFGH-IJKL', 'magic_world', 'install-browser-a');
        $this->assertTrue($sameBrowser['ok']);
        $this->assertNotSame($claim['device_token'], $sameBrowser['device_token']);
        $this->assertSame('INVALID_DEVICE', $this->licenses->verify($claim['device_token'], 'magic_world', 'install-browser-a')['code']);
        $this->assertTrue($this->licenses->verify($sameBrowser['device_token'], 'magic_world', 'install-browser-a')['ok']);
        $this->assertDatabaseHas('license_binding_events', ['event_type' => 'reclaimed_same_device']);

        $otherBrowser = $this->licenses->claim('YYJP-ABCD-EFGH-IJKL', 'magic_world', 'install-browser-b');
        $this->assertSame('RECOVERY_REQUIRED', $otherBrowser['code']);
        $this->assertSame('b***@qq.com', $otherBrowser['masked_email']);

        $otherGame = $this->licenses->claim('YYJP-ABCD-EFGH-IJKL', 'streamer_simulator', 'install-browser-b');
        $this->assertSame('WRONG_GAME', $otherGame['code']);
    }

    public function test_email_recovery_rotates_token_and_challenge_can_only_be_used_once(): void
    {
        Queue::fake();
        $this->seedSoldPlusCode('YYJP-ABCD-EFGH-IJKL');
        $old = $this->licenses->claim('YYJP-ABCD-EFGH-IJKL', 'hougong_fenghualu', 'install-browser-a');

        $request = $this->licenses->requestRecovery('YYJP-ABCD-EFGH-IJKL', 'hougong_fenghualu', '127.0.0.1');
        $this->assertSame('OTP_SENT', $request['code']);
        Queue::assertPushed(SendLicenseRecoveryCode::class);

        $challenge = DB::table('license_recovery_challenges')->where('id', $request['challenge_id'])->first();
        $otp = Crypt::decryptString($challenge->otp_cipher);
        $this->assertStringNotContainsString($otp, $challenge->otp_cipher);

        $replacement = $this->licenses->confirmRecovery($request['challenge_id'], $otp, 'hougong_fenghualu', 'install-browser-b');
        $this->assertTrue($replacement['ok']);
        $this->assertSame('INVALID_DEVICE', $this->licenses->verify($old['device_token'], 'hougong_fenghualu', 'install-browser-a')['code']);
        $this->assertTrue($this->licenses->verify($replacement['device_token'], 'hougong_fenghualu', 'install-browser-b')['ok']);
        $this->assertSame('INVALID_OTP', $this->licenses->confirmRecovery($request['challenge_id'], $otp, 'hougong_fenghualu', 'install-browser-c')['code']);
    }

    public function test_historical_order_requires_email_before_first_binding(): void
    {
        Queue::fake();
        $this->seedSoldPlusCode('YYJP-ABCD-EFGH-IJKL', true);

        $claim = $this->licenses->claim('YYJP-ABCD-EFGH-IJKL', 'streamer_simulator', 'install-browser-a');
        $this->assertSame('RECOVERY_REQUIRED', $claim['code']);

        $request = $this->licenses->requestRecovery('YYJP-ABCD-EFGH-IJKL', 'streamer_simulator');
        $challenge = DB::table('license_recovery_challenges')->where('id', $request['challenge_id'])->first();
        $otp = Crypt::decryptString($challenge->otp_cipher);
        $confirmed = $this->licenses->confirmRecovery($request['challenge_id'], $otp, 'streamer_simulator', 'install-browser-a');
        $this->assertTrue($confirmed['ok']);
    }

    public function test_otp_is_locked_after_five_wrong_attempts(): void
    {
        Queue::fake();
        $this->seedSoldPlusCode('YYJP-ABCD-EFGH-IJKL', true);
        $request = $this->licenses->requestRecovery('YYJP-ABCD-EFGH-IJKL', 'magic_world');
        $cipher = DB::table('license_recovery_challenges')->where('id', $request['challenge_id'])->value('otp_cipher');
        $wrongOtp = Crypt::decryptString($cipher) === '000000' ? '111111' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $result = $this->licenses->confirmRecovery($request['challenge_id'], $wrongOtp, 'magic_world', 'install-browser-a');
            $this->assertSame('INVALID_OTP', $result['code']);
        }

        $locked = $this->licenses->confirmRecovery($request['challenge_id'], $wrongOtp, 'magic_world', 'install-browser-a');
        $this->assertSame('OTP_LOCKED', $locked['code']);
        $this->assertDatabaseHas('license_recovery_challenges', ['id' => $request['challenge_id'], 'attempts' => 5]);
    }

    public function test_license_preflight_is_public_and_has_no_cookie_cors_headers(): void
    {
        $response = $this->call('OPTIONS', '/api/v1/licenses/claim');
        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin', '*');
        $response->assertHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
    }

    public function test_non_plus_sku_does_not_create_a_license(): void
    {
        [$order, $carmisId] = $this->seedSoldPlusCode('YYJP-ABCD-EFGH-IJKL', false, 'ORDINARY');
        $this->assertSame(0, $this->licenses->registerSoldCarmis([$carmisId], $order));
        $this->assertSame(0, GameLicense::query()->count());
    }

    public function test_legacy_standalone_plus_product_requires_an_explicit_goods_allowlist(): void
    {
        [$order, $carmisId] = $this->seedSoldPlusCode('YYJP-ABCD-EFGH-IJKL', false, 'DEFAULT');
        DB::table('goods_skus')->where('id', $order->sku_id)->update(['deleted_at' => now()]);
        DB::table('carmis')->where('id', $carmisId)->update(['deleted_at' => now()]);

        $this->assertSame(0, $this->licenses->registerSoldCarmis([$carmisId], $order, true));
        $this->assertSame(1, $this->licenses->registerSoldCarmis([$carmisId], $order, true, [27]));
        $this->assertDatabaseHas('game_licenses', [
            'carmis_id' => $carmisId,
            'order_id' => $order->id,
            'is_legacy' => 1,
            'requires_email_verification' => 1,
        ]);
    }

    private function seedSoldPlusCode(string $code, bool $legacy = false, string $skuCode = 'GAME_PLUS'): array
    {
        $now = now();
        $skuId = DB::table('goods_skus')->insertGetId([
            'goods_id' => 27,
            'sku_name' => $skuCode === 'GAME_PLUS' ? '解锁码plus' : '普通解锁码',
            'sku_code' => $skuCode,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'order_sn' => 'TEST-' . uniqid(),
            'sku_id' => $skuId,
            'email' => 'ben@qq.com',
            'status' => Order::STATUS_COMPLETED,
            'info' => $code,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $carmisId = DB::table('carmis')->insertGetId([
            'goods_id' => 27,
            'sku_id' => $skuId,
            'carmi' => $code,
            'is_loop' => 0,
            'status' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $order = Order::query()->findOrFail($orderId);
        if ($skuCode === 'GAME_PLUS') {
            $this->licenses->registerSoldCarmis([$carmisId], $order, $legacy);
        }
        return [$order, $carmisId];
    }

    private function createTables(): void
    {
        foreach (['license_binding_events', 'license_recovery_challenges', 'game_licenses', 'carmis', 'orders', 'goods_skus'] as $table) {
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
            $table->string('order_sn');
            $table->integer('sku_id');
            $table->string('email');
            $table->integer('status');
            $table->text('info')->nullable();
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
        Schema::create('license_recovery_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('license_id');
            $table->char('otp_hash', 64);
            $table->text('otp_cipher')->nullable();
            $table->char('requested_ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('license_binding_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('license_id');
            $table->string('event_type');
            $table->char('from_install_hash', 64)->nullable();
            $table->char('to_install_hash', 64)->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
}
