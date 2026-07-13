<?php

namespace App\Jobs;

use App\Models\LicenseRecoveryChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;

class SendLicenseRecoveryCode implements ShouldQueue
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
        $challenge = LicenseRecoveryChallenge::query()
            ->with('license.order')
            ->find($this->challengeId);
        if (!$challenge || !$challenge->otp_cipher || !$challenge->license || !$challenge->license->order) {
            return;
        }

        $otp = Crypt::decryptString($challenge->otp_cipher);
        $minutes = (int) config('licenses.otp_minutes', 10);
        $content = '<p>您正在转移一张解锁码 Plus 的浏览器授权。</p>'
            . '<p>验证码：<strong style="font-size:24px;letter-spacing:4px">' . e($otp) . '</strong></p>'
            . '<p>验证码将在 ' . $minutes . ' 分钟后失效。如果不是您本人操作，请忽略这封邮件。</p>';

        $mail = new MailSend($challenge->license->order->email, '解锁码 Plus 找回验证码', $content);
        $mail->handle();

        $challenge->otp_cipher = null;
        $challenge->save();
    }
}
