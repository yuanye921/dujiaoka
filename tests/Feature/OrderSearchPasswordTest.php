<?php

namespace Tests\Feature;

use App\Rules\SearchPwd;
use App\Service\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderSearchPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::put('system-setting', [
            'template' => 'yuyanjia',
            'is_open_search_pwd' => 0,
        ]);

        foreach (['orders', 'coupons', 'pays', 'goods', 'goods_skus'] as $table) {
            Schema::dropIfExists($table);
        }

        foreach (['coupons', 'pays', 'goods', 'goods_skus'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->bigIncrements('id');
                $blueprint->softDeletes();
            });
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_sn')->unique();
            $table->string('email');
            $table->string('search_pwd')->default('');
            $table->integer('status')->default(4);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_email_lookup_rejects_a_missing_password_even_for_a_legacy_order(): void
    {
        $this->seedOrder('LEGACY-001', 'buyer@qq.com', '');

        $orders = app(OrderService::class)->withEmailAndPassword('buyer@qq.com', '');

        $this->assertCount(0, $orders);
    }

    public function test_email_lookup_returns_only_orders_with_the_matching_password(): void
    {
        $this->seedOrder('ORDER-001', 'buyer@qq.com', 'correct-password');
        $this->seedOrder('ORDER-002', 'buyer@qq.com', 'another-password');

        $orders = app(OrderService::class)->withEmailAndPassword('buyer@qq.com', 'correct-password');

        $this->assertCount(1, $orders);
        $this->assertSame('ORDER-001', $orders->first()->order_sn);
    }

    public function test_order_password_is_required_when_the_old_setting_is_off(): void
    {
        $this->assertFalse((new SearchPwd())->passes('search_pwd', ''));
    }

    /**
     * @dataProvider searchThemeProvider
     */
    public function test_email_search_form_shows_a_required_password_when_the_old_setting_is_off(string $theme): void
    {
        $html = view($theme . '.static_pages.searchOrder', [
            'page_title' => '查询订单',
        ])->render();

        $this->assertStringContainsString('name="search_pwd"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+name="search_pwd"[^>]+required/', $html);
    }

    public function searchThemeProvider(): array
    {
        return [
            'yuyanjia' => ['yuyanjia'],
            'hyper' => ['hyper'],
            'luna' => ['luna'],
            'unicorn' => ['unicorn'],
        ];
    }

    private function seedOrder(string $orderSn, string $email, string $searchPassword): void
    {
        DB::table('orders')->insert([
            'order_sn' => $orderSn,
            'email' => $email,
            'search_pwd' => $searchPassword,
            'status' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
