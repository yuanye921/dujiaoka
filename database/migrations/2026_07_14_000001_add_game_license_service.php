<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddGameLicenseService extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('game_licenses')) {
            Schema::create('game_licenses', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('carmis_id')->index();
                $table->bigInteger('order_id')->index();
                $table->integer('sku_id')->index();
                $table->char('code_hash', 64)->unique();
                $table->string('game_id', 64)->nullable()->index();
                $table->char('device_token_hash', 64)->nullable()->index();
                $table->char('install_id_hash', 64)->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->boolean('is_legacy')->default(false)->index();
                $table->boolean('requires_email_verification')->default(false)->index();
                $table->unsignedInteger('binding_version')->default(0);
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamp('recovery_override_until')->nullable();
                $table->timestamps();
                $table->unique('carmis_id', 'game_licenses_carmis_id_unique');
            });
        }

        if (!Schema::hasTable('license_recovery_challenges')) {
            Schema::create('license_recovery_challenges', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->bigInteger('license_id')->index();
                $table->char('otp_hash', 64);
                $table->text('otp_cipher')->nullable();
                $table->char('requested_ip_hash', 64)->nullable()->index();
                $table->char('user_agent_hash', 64)->nullable();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at')->index();
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('license_binding_events')) {
            Schema::create('license_binding_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('license_id')->index();
                $table->string('event_type', 32)->index();
                $table->char('from_install_hash', 64)->nullable();
                $table->char('to_install_hash', 64)->nullable();
                $table->char('ip_hash', 64)->nullable()->index();
                $table->char('user_agent_hash', 64)->nullable();
                $table->text('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (Schema::hasTable('goods_skus')) {
            DB::table('goods_skus')
                ->where('sku_name', '解锁码plus')
                ->update(['sku_code' => 'GAME_PLUS']);
        }

        $this->insertAdminMenu();
    }

    public function down()
    {
        if (Schema::hasTable('admin_menu')) {
            DB::table('admin_menu')->where('uri', '/game-license')->delete();
        }

        Schema::dropIfExists('license_binding_events');
        Schema::dropIfExists('license_recovery_challenges');
        Schema::dropIfExists('game_licenses');
    }

    private function insertAdminMenu()
    {
        if (!Schema::hasTable('admin_menu') || DB::table('admin_menu')->where('uri', '/game-license')->exists()) {
            return;
        }

        DB::table('admin_menu')->insert([
            'parent_id' => 0,
            'order' => 30,
            'title' => 'Game_Licenses',
            'icon' => 'fa-key',
            'uri' => '/game-license',
            'extension' => '',
            'show' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
