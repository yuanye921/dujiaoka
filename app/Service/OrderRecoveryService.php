<?php

namespace App\Service;

use App\Jobs\SendOrderRecoveryCode;
use App\Models\Order;
use App\Models\OrderRecoveryChallenge;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderRecoveryService
{
    public function request(string $email, string $ip = '', string $userAgent = ''): array
    {
        $email = $this->normalizeEmail($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('请输入有效的购买邮箱。');
        }

        $now = Carbon::now();
        $emailHash = $this->privacyHash($email);
        $ipHash = $this->privacyHash($ip);
        $lastChallenge = OrderRecoveryChallenge::query()
            ->where('email_hash', $emailHash)
            ->orderBy('created_at', 'desc')
            ->first();
        if ($lastChallenge && $lastChallenge->created_at->gt($now->copy()->subMinute())) {
            return $this->error('验证码发送得太频繁，请一分钟后再试。');
        }
        if (OrderRecoveryChallenge::query()->where('email_hash', $emailHash)->where('created_at', '>=', $now->copy()->subHour())->count() >= 5) {
            return $this->error('这个邮箱一小时内请求得太频繁，请稍后再试。');
        }
        if ($ipHash && OrderRecoveryChallenge::query()->where('requested_ip_hash', $ipHash)->where('created_at', '>=', $now->copy()->subHour())->count() >= 20) {
            return $this->error('当前网络请求得太频繁，请稍后再试。');
        }

        $hasOrders = $this->ordersForEmail($email)->exists();
        $challengeId = (string) Str::uuid();
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = OrderRecoveryChallenge::query()->create([
            'id' => $challengeId,
            'email_hash' => $emailHash,
            'email_cipher' => Crypt::encryptString($email),
            'otp_hash' => $this->otpHash($challengeId, $otp),
            'otp_cipher' => $hasOrders ? Crypt::encryptString($otp) : null,
            'requested_ip_hash' => $ipHash,
            'user_agent_hash' => $this->privacyHash($userAgent),
            'has_orders' => $hasOrders,
            'attempts' => 0,
            'expires_at' => $now->copy()->addMinutes(max(1, (int) config('licenses.otp_minutes', 10))),
        ]);

        // 已存在和不存在的邮箱都投递同一种队列任务，避免从响应耗时判断邮箱是否存在。
        // 不存在订单的挑战没有验证码密文，队列任务会安全地直接结束。
        SendOrderRecoveryCode::dispatch($challenge->id);

        return [
            'ok' => true,
            'challenge_id' => $challenge->id,
            'masked_email' => $this->maskEmail($email),
            'message' => '如果该邮箱存在订单，验证码会发送到邮箱，请检查收件箱和垃圾箱。',
        ];
    }

    public function confirm(string $challengeId, string $otp): array
    {
        return DB::transaction(function () use ($challengeId, $otp) {
            $challenge = OrderRecoveryChallenge::query()->where('id', trim($challengeId))->lockForUpdate()->first();
            if (!$challenge || $challenge->used_at) {
                return $this->error('验证码无效或已经使用。');
            }

            $maskedEmail = $this->maskEmail(Crypt::decryptString($challenge->email_cipher));
            if ($challenge->expires_at->isPast()) {
                return $this->error('验证码已经过期，请重新发送。', $challenge->id, $maskedEmail);
            }

            $maxAttempts = max(1, (int) config('licenses.otp_max_attempts', 5));
            if ($challenge->attempts >= $maxAttempts) {
                return $this->error('验证码错误次数过多，请重新发送。', $challenge->id, $maskedEmail);
            }

            if (!$challenge->has_orders || !hash_equals((string) $challenge->otp_hash, $this->otpHash($challenge->id, trim($otp)))) {
                $challenge->attempts++;
                $challenge->save();
                return $this->error('验证码不正确。', $challenge->id, $maskedEmail);
            }

            $challenge->used_at = Carbon::now();
            $challenge->otp_cipher = null;
            $challenge->save();

            return ['ok' => true, 'challenge_id' => $challenge->id];
        }, 3);
    }

    public function verifiedEmail(string $challengeId, int $sessionMinutes = 30): ?string
    {
        $sessionMinutes = max(1, $sessionMinutes);
        $challenge = OrderRecoveryChallenge::query()
            ->where('id', $challengeId)
            ->where('has_orders', true)
            ->whereNotNull('used_at')
            ->where('used_at', '>=', Carbon::now()->subMinutes($sessionMinutes))
            ->first();

        return $challenge ? Crypt::decryptString($challenge->email_cipher) : null;
    }

    public function ordersForEmail(string $email)
    {
        return Order::query()->whereRaw('LOWER(TRIM(email)) = ?', [$this->normalizeEmail($email)]);
    }

    public function maskEmail(string $email): string
    {
        $parts = explode('@', $this->normalizeEmail($email), 2);
        if (count($parts) !== 2) {
            return '***';
        }
        return substr($parts[0], 0, 1) . '***@' . $parts[1];
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function otpHash(string $challengeId, string $otp): string
    {
        return hash_hmac('sha256', $challengeId . '|' . $otp, (string) config('licenses.otp_pepper', config('app.key')));
    }

    private function privacyHash(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return hash_hmac('sha256', $value, (string) config('licenses.privacy_pepper', config('app.key')));
    }

    private function error(string $message, string $challengeId = '', string $maskedEmail = ''): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'challenge_id' => $challengeId,
            'masked_email' => $maskedEmail,
        ];
    }
}
