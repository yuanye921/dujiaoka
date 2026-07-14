<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOrderRecoveryChallenges extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('order_recovery_challenges')) {
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

    public function down()
    {
        Schema::dropIfExists('order_recovery_challenges');
    }
}
