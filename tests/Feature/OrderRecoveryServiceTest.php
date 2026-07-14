<?php

namespace Tests\Feature;

use App\Jobs\SendOrderRecoveryCode;
use App\Service\OrderRecoveryService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderRecoveryServiceTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'licenses.otp_minutes' => 10,
            'licenses.otp_max_attempts' => 5,
            'licenses.otp_pepper' => 'order-recovery-otp-test-pepper',
            'licenses.privacy_pepper' => 'order-recovery-privacy-test-pepper',
        ]);
        $this->createTables();
        $this->service = app(OrderRecoveryService::class);
    }

    public function test_known_email_receives_one_time_code_and_can_open_orders(): void
    {
        Queue::fake();
        $this->seedOrder('Legacy.User@Example.com ');

        $requested = $this->service->request(' legacy.user@example.com', '127.0.0.1', 'test-browser');

        $this->assertTrue($requested['ok']);
        $this->assertSame('l***@example.com', $requested['masked_email']);
        Queue::assertPushed(SendOrderRecoveryCode::class, 1);

        $challenge = DB::table('order_recovery_challenges')->where('id', $requested['challenge_id'])->first();
        $otp = Crypt::decryptString($challenge->otp_cipher);
        $this->assertStringNotContainsString($otp, $challenge->otp_cipher);
        $this->assertTrue((bool) $challenge->has_orders);

        $confirmed = $this->service->confirm($challenge->id, $otp);
        $this->assertTrue($confirmed['ok']);
        $this->assertSame('legacy.user@example.com', $this->service->verifiedEmail($challenge->id));
        $this->assertSame(1, $this->service->ordersForEmail('LEGACY.USER@example.com')->count());

        $replayed = $this->service->confirm($challenge->id, $otp);
        $this->assertFalse($replayed['ok']);
    }

    public function test_unknown_email_gets_same_public_response_and_a_noop_mail_job(): void
    {
        Queue::fake();

        $requested = $this->service->request('missing@example.com', '127.0.0.2', 'test-browser');

        $this->assertTrue($requested['ok']);
        $this->assertStringContainsString('如果该邮箱存在订单', $requested['message']);
        Queue::assertPushed(SendOrderRecoveryCode::class, 1);
        $this->assertDatabaseHas('order_recovery_challenges', [
            'id' => $requested['challenge_id'],
            'has_orders' => 0,
        ]);
    }

    public function test_code_expires_and_is_locked_after_five_wrong_attempts(): void
    {
        Queue::fake();
        $this->seedOrder('buyer@example.com');
        $requested = $this->service->request('buyer@example.com');
        $challenge = DB::table('order_recovery_challenges')->where('id', $requested['challenge_id'])->first();
        $otp = Crypt::decryptString($challenge->otp_cipher);
        $wrongOtp = $otp === '000000' ? '111111' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertFalse($this->service->confirm($challenge->id, $wrongOtp)['ok']);
        }
        $locked = $this->service->confirm($challenge->id, $otp);
        $this->assertFalse($locked['ok']);
        $this->assertStringContainsString('错误次数过多', $locked['message']);

        DB::table('order_recovery_challenges')->where('id', $challenge->id)->update([
            'attempts' => 0,
            'expires_at' => Carbon::now()->subMinute(),
        ]);
        $expired = $this->service->confirm($challenge->id, $otp);
        $this->assertFalse($expired['ok']);
        $this->assertStringContainsString('已经过期', $expired['message']);
    }

    public function test_verified_email_session_expires_after_thirty_minutes(): void
    {
        Queue::fake();
        $this->seedOrder('buyer@example.com');
        $requested = $this->service->request('buyer@example.com');
        $challenge = DB::table('order_recovery_challenges')->where('id', $requested['challenge_id'])->first();
        $otp = Crypt::decryptString($challenge->otp_cipher);
        $this->assertTrue($this->service->confirm($challenge->id, $otp)['ok']);

        DB::table('order_recovery_challenges')->where('id', $challenge->id)->update([
            'used_at' => Carbon::now()->subMinutes(31),
        ]);

        $this->assertNull($this->service->verifiedEmail($challenge->id, 30));
    }

    private function seedOrder(string $email): void
    {
        DB::table('orders')->insert([
            'order_sn' => 'LEGACY-' . uniqid(),
            'email' => $email,
            'status' => 4,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('order_recovery_challenges');
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_sn');
            $table->string('email');
            $table->integer('status');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('order_recovery_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('email_hash', 64)->index();
            $table->text('email_cipher');
            $table->char('otp_hash', 64);
            $table->text('otp_cipher')->nullable();
            $table->char('requested_ip_hash', 64)->nullable()->index();
            $table->char('user_agent_hash', 64)->nullable();
            $table->boolean('has_orders')->default(false)->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamps();
        });
    }
}
