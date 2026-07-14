<?php

namespace App\Jobs;

use App\Models\OrderRecoveryChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;

class SendOrderRecoveryCode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    private $challengeId;

    public function __construct(string $challengeId)
    {
        $this->challengeId = $challengeId;
    }

    public function handle()
    {
        $challenge = OrderRecoveryChallenge::query()->find($this->challengeId);
        if (!$challenge || !$challenge->has_orders || !$challenge->otp_cipher || $challenge->used_at || $challenge->expires_at->isPast()) {
            return;
        }

        $email = Crypt::decryptString($challenge->email_cipher);
        $otp = Crypt::decryptString($challenge->otp_cipher);
        $minutes = max(1, (int) config('licenses.otp_minutes', 10));
        $content = '<p>您正在查看购买邮箱下的历史订单。</p>'
            . '<p>验证码：<strong style="font-size:24px;letter-spacing:4px">' . e($otp) . '</strong></p>'
            . '<p>验证码将在 ' . $minutes . ' 分钟后失效。如果不是您本人操作，请忽略这封邮件。</p>';

        $mail = new MailSend($email, '历史订单找回验证码', $content);
        $mail->handle();

        $challenge->otp_cipher = null;
        $challenge->save();
    }
}
