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
        $minutes = max(1, (int) config('licenses.otp_minutes', 10));
        $content = view('email.verification_code', [
            'heading' => '解锁码找回验证码',
            'subheading' => '请使用下方验证码完成浏览器授权转移。',
            'intro' => '您正在转移一张解锁码 Plus 的浏览器授权。',
            'code' => $otp,
            'minutes' => $minutes,
        ])->render();

        $mail = new MailSend($challenge->license->order->email, '解锁码找回验证码', $content);
        $mail->handle();

        $challenge->otp_cipher = null;
        $challenge->save();
    }
}
