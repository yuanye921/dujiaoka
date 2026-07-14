<?php

namespace App\Service;

use App\Jobs\SendLicenseRecoveryCode;
use App\Models\Carmis;
use App\Models\GameLicense;
use App\Models\GoodsSku;
use App\Models\LicenseBindingEvent;
use App\Models\LicenseRecoveryChallenge;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class GameLicenseService
{
    const CODE_PATTERN = '/^YYJP-[A-Z0-9]{4}(?:-[A-Z0-9]{4}){2}$/';

    public function registerSoldCarmis(array $carmisIds, Order $order, bool $legacy = false, array $legacyGoodsIds = []): int
    {
        $skuQuery = $legacy ? GoodsSku::withTrashed() : GoodsSku::query();
        $sku = $skuQuery->find($order->sku_id);
        if (!$sku || !$this->isPlusSku($sku, $legacy ? $legacyGoodsIds : [])) {
            return 0;
        }

        $carmisQuery = $legacy ? Carmis::withTrashed() : Carmis::query();
        $carmis = $carmisQuery->whereIn('id', $carmisIds)->get();
        if ($carmis->count() !== count($carmisIds)) {
            throw new RuntimeException('Plus license inventory could not be resolved completely.');
        }

        $registered = 0;
        foreach ($carmis as $item) {
            if ((int) $item->is_loop !== 0 || (int) $item->sku_id !== (int) $order->sku_id) {
                throw new RuntimeException('Plus licenses must use non-looping inventory from the purchased SKU.');
            }

            $normalized = $this->normalizeCode($item->carmi);
            if (!$this->isValidCode($normalized)) {
                throw new RuntimeException('A Plus inventory code has an invalid format.');
            }

            $codeHash = $this->codeHash($normalized);
            $collision = GameLicense::query()
                ->where('code_hash', $codeHash)
                ->where('carmis_id', '<>', $item->id)
                ->exists();
            if ($collision) {
                throw new RuntimeException('A duplicate Plus license code was detected.');
            }

            $license = GameLicense::query()->firstOrNew(['carmis_id' => $item->id]);
            if ($license->exists && ((int) $license->order_id !== (int) $order->id || !hash_equals((string) $license->code_hash, $codeHash))) {
                throw new RuntimeException('An existing Plus license cannot be reassigned to another order or code.');
            }
            $license->order_id = $order->id;
            $license->sku_id = $order->sku_id;
            $license->code_hash = $codeHash;
            $license->status = $license->status ?: GameLicense::STATUS_ACTIVE;
            $license->is_legacy = $license->exists ? $license->is_legacy : $legacy;
            $license->requires_email_verification = $license->exists
                ? $license->requires_email_verification
                : $legacy;
            $license->save();
            $registered++;
        }

        return $registered;
    }

    public function claim(string $code, string $gameId, string $installId, string $ip = '', string $userAgent = ''): array
    {
        if (!$this->isValidGame($gameId)) {
            return $this->error('INVALID_GAME', '当前游戏没有登记到授权服务。', 422);
        }

        $normalized = $this->normalizeCode($code);
        if (!$this->isValidCode($normalized)) {
            return $this->error('INVALID_CODE', '没有找到这个解锁码 Plus。', 422);
        }

        return DB::transaction(function () use ($normalized, $gameId, $installId, $ip, $userAgent) {
            $license = GameLicense::query()
                ->with('order')
                ->where('code_hash', $this->codeHash($normalized))
                ->lockForUpdate()
                ->first();

            if (!$license) {
                return $this->error('INVALID_CODE', '没有找到这个解锁码 Plus。', 422);
            }
            if ($license->status !== GameLicense::STATUS_ACTIVE) {
                return $this->error('LICENSE_REVOKED', '这张解锁码当前已被停用，请联系售后。', 403);
            }
            if ($license->game_id && $license->game_id !== $gameId) {
                return $this->error('WRONG_GAME', '这张解锁码已经属于另一款游戏，不能重复使用。', 409);
            }

            $override = $license->recovery_override_until && $license->recovery_override_until->isFuture();
            $sameInstall = $license->device_token_hash &&
                $license->install_id_hash &&
                hash_equals((string) $license->install_id_hash, $this->installHash($installId));
            if (!$override && !$license->requires_email_verification && $sameInstall) {
                return $this->rotateBinding($license, $gameId, $installId, $ip, $userAgent, 'reclaimed_same_device');
            }
            if (!$override && ($license->requires_email_verification || $license->device_token_hash)) {
                return $this->error(
                    'RECOVERY_REQUIRED',
                    '这张解锁码已经绑定过浏览器，请通过订单邮箱验证后转移。',
                    409,
                    ['masked_email' => $this->maskedOrderEmail($license)]
                );
            }

            return $this->rotateBinding($license, $gameId, $installId, $ip, $userAgent, 'claimed');
        }, 3);
    }

    public function verify(string $deviceToken, string $gameId, string $installId, string $ip = '', string $userAgent = ''): array
    {
        if (!$this->isValidGame($gameId) || trim($deviceToken) === '') {
            return $this->error('INVALID_DEVICE', '当前浏览器没有有效授权。', 401);
        }

        $license = GameLicense::query()
            ->where('device_token_hash', $this->tokenHash($deviceToken))
            ->first();

        if (!$license ||
            $license->status !== GameLicense::STATUS_ACTIVE ||
            $license->game_id !== $gameId ||
            !hash_equals((string) $license->install_id_hash, $this->installHash($installId))) {
            return $this->error('INVALID_DEVICE', '授权已经转移、撤销或不属于当前浏览器。', 401);
        }

        $license->last_verified_at = Carbon::now();
        $license->save();
        $this->recordEvent($license, 'verified', $license->install_id_hash, $license->install_id_hash, $ip, $userAgent);

        return $this->activePayload($license, null);
    }

    public function requestRecovery(string $code, string $gameId, string $ip = '', string $userAgent = ''): array
    {
        if (!$this->isValidGame($gameId)) {
            return $this->error('INVALID_GAME', '当前游戏没有登记到授权服务。', 422);
        }

        $normalized = $this->normalizeCode($code);
        if (!$this->isValidCode($normalized)) {
            return $this->error('INVALID_CODE', '没有找到可以找回的购买记录。', 422);
        }

        $license = GameLicense::query()
            ->with('order')
            ->where('code_hash', $this->codeHash($normalized))
            ->first();
        if (!$license || $license->status !== GameLicense::STATUS_ACTIVE || !$license->order) {
            return $this->error('INVALID_CODE', '没有找到可以找回的购买记录。', 422);
        }
        if ($license->game_id && $license->game_id !== $gameId) {
            return $this->error('WRONG_GAME', '这张解锁码已经属于另一款游戏。', 409);
        }
        if (!filter_var($license->order->email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('EMAIL_UNAVAILABLE', '订单邮箱不可用，请凭订单记录联系售后。', 409);
        }

        $ipHash = $this->privacyHash($ip);
        $now = Carbon::now();
        $lastChallenge = LicenseRecoveryChallenge::query()
            ->where('license_id', $license->id)
            ->orderBy('created_at', 'desc')
            ->first();
        if ($lastChallenge && $lastChallenge->created_at->gt($now->copy()->subMinute())) {
            return $this->error('RATE_LIMITED', '验证码发送得太频繁，请一分钟后再试。', 429);
        }
        if (LicenseRecoveryChallenge::query()->where('license_id', $license->id)->where('created_at', '>=', $now->copy()->subHour())->count() >= 5) {
            return $this->error('RATE_LIMITED', '这张解锁码一小时内请求得太频繁，请稍后再试。', 429);
        }
        if ($ipHash && LicenseRecoveryChallenge::query()->where('requested_ip_hash', $ipHash)->where('created_at', '>=', $now->copy()->subHour())->count() >= 20) {
            return $this->error('RATE_LIMITED', '当前网络请求得太频繁，请稍后再试。', 429);
        }

        $challengeId = (string) Str::uuid();
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = LicenseRecoveryChallenge::query()->create([
            'id' => $challengeId,
            'license_id' => $license->id,
            'otp_hash' => $this->otpHash($challengeId, $otp),
            'otp_cipher' => Crypt::encryptString($otp),
            'requested_ip_hash' => $ipHash,
            'user_agent_hash' => $this->privacyHash($userAgent),
            'attempts' => 0,
            'expires_at' => $now->copy()->addMinutes((int) config('licenses.otp_minutes', 10)),
        ]);

        SendLicenseRecoveryCode::dispatch($challenge->id);

        return [
            'ok' => true,
            'code' => 'OTP_SENT',
            'message' => '验证码已经发送到订单邮箱。',
            'challenge_id' => $challenge->id,
            'masked_email' => $this->maskEmail($license->order->email),
            'expires_in' => (int) config('licenses.otp_minutes', 10) * 60,
            'http_status' => 200,
        ];
    }

    public function confirmRecovery(string $challengeId, string $otp, string $gameId, string $installId, string $ip = '', string $userAgent = ''): array
    {
        if (!$this->isValidGame($gameId)) {
            return $this->error('INVALID_GAME', '当前游戏没有登记到授权服务。', 422);
        }

        return DB::transaction(function () use ($challengeId, $otp, $gameId, $installId, $ip, $userAgent) {
            $challenge = LicenseRecoveryChallenge::query()->where('id', $challengeId)->lockForUpdate()->first();
            if (!$challenge || $challenge->used_at) {
                return $this->error('INVALID_OTP', '验证码无效或已经使用。', 422);
            }
            if ($challenge->expires_at->isPast()) {
                return $this->error('OTP_EXPIRED', '验证码已经过期，请重新发送。', 410);
            }

            $maxAttempts = (int) config('licenses.otp_max_attempts', 5);
            if ($challenge->attempts >= $maxAttempts) {
                return $this->error('OTP_LOCKED', '验证码错误次数过多，请重新发送。', 429);
            }
            if (!hash_equals($challenge->otp_hash, $this->otpHash($challenge->id, trim($otp)))) {
                $challenge->attempts++;
                $challenge->save();
                return $this->error('INVALID_OTP', '验证码不正确。', 422, [
                    'remaining_attempts' => max(0, $maxAttempts - $challenge->attempts),
                ]);
            }

            $license = GameLicense::query()->where('id', $challenge->license_id)->lockForUpdate()->first();
            if (!$license || $license->status !== GameLicense::STATUS_ACTIVE) {
                return $this->error('LICENSE_REVOKED', '授权已经被停用，请联系售后。', 403);
            }
            if ($license->game_id && $license->game_id !== $gameId) {
                return $this->error('WRONG_GAME', '这张解锁码已经属于另一款游戏。', 409);
            }

            $challenge->used_at = Carbon::now();
            $challenge->otp_cipher = null;
            $challenge->save();

            return $this->rotateBinding($license, $gameId, $installId, $ip, $userAgent, 'recovered');
        }, 3);
    }

    public function allowOneTimeRecovery(GameLicense $license, int $minutes = 30, array $metadata = []): void
    {
        $license->status = GameLicense::STATUS_ACTIVE;
        $license->recovery_override_until = Carbon::now()->addMinutes($minutes);
        $license->save();
        $this->recordEvent($license, 'admin_override', $license->install_id_hash, null, '', '', array_merge(['minutes' => $minutes], $metadata));
    }

    public function revoke(GameLicense $license, array $metadata = []): void
    {
        $fromInstall = $license->install_id_hash;
        $license->status = GameLicense::STATUS_REVOKED;
        $license->device_token_hash = null;
        $license->install_id_hash = null;
        $license->binding_version++;
        $license->save();
        $this->recordEvent($license, 'revoked', $fromInstall, null, '', '', $metadata);
    }

    public function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', str_replace(['—', '–', '－'], '-', trim($code))));
    }

    public function isValidCode(string $code): bool
    {
        return preg_match(self::CODE_PATTERN, $code) === 1;
    }

    public function codeHash(string $normalizedCode): string
    {
        return $this->keyedHash($normalizedCode, 'code_pepper');
    }

    public function maskCode(string $code): string
    {
        $parts = explode('-', $this->normalizeCode($code));
        if (count($parts) < 2) {
            return '****';
        }
        for ($i = 1; $i < count($parts) - 1; $i++) {
            $parts[$i] = '****';
        }
        return implode('-', $parts);
    }

    public function maskEmail(string $email): string
    {
        $parts = explode('@', trim($email), 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $local = $parts[0];
        $visible = substr($local, 0, 1);
        return $visible . '***@' . $parts[1];
    }

    private function isPlusSku(GoodsSku $sku, array $legacyGoodsIds = []): bool
    {
        if (strtoupper(trim((string) $sku->sku_code)) === strtoupper((string) config('licenses.plus_sku_code', 'GAME_PLUS'))) {
            return true;
        }

        $legacyGoodsIds = array_map('intval', $legacyGoodsIds);
        return in_array((int) $sku->goods_id, $legacyGoodsIds, true);
    }

    private function isValidGame(string $gameId): bool
    {
        return array_key_exists($gameId, (array) config('licenses.games', []));
    }

    private function rotateBinding(GameLicense $license, string $gameId, string $installId, string $ip, string $userAgent, string $eventType): array
    {
        $token = bin2hex(random_bytes(32));
        $fromInstall = $license->install_id_hash;
        $license->game_id = $license->game_id ?: $gameId;
        $license->device_token_hash = $this->tokenHash($token);
        $license->install_id_hash = $this->installHash($installId);
        $license->requires_email_verification = false;
        $license->recovery_override_until = null;
        $license->binding_version++;
        $license->claimed_at = $license->claimed_at ?: Carbon::now();
        $license->last_verified_at = Carbon::now();
        $license->save();

        $this->recordEvent($license, $eventType, $fromInstall, $license->install_id_hash, $ip, $userAgent);

        return $this->activePayload($license, $token);
    }

    private function activePayload(GameLicense $license, ?string $token): array
    {
        $payload = [
            'ok' => true,
            'code' => 'ACTIVE',
            'message' => '授权验证成功。',
            'game_id' => $license->game_id,
            'activated_at' => optional($license->claimed_at)->toIso8601String(),
            'lease_until' => Carbon::now()->addHours((int) config('licenses.lease_hours', 24))->toIso8601String(),
            'binding_version' => (int) $license->binding_version,
            'http_status' => 200,
        ];
        if ($token !== null) {
            $payload['device_token'] = $token;
        }
        return $payload;
    }

    private function maskedOrderEmail(GameLicense $license): string
    {
        return $license->order ? $this->maskEmail((string) $license->order->email) : '***';
    }

    private function tokenHash(string $token): string
    {
        return $this->keyedHash($token, 'token_pepper');
    }

    private function installHash(string $installId): string
    {
        return $this->keyedHash(trim($installId), 'privacy_pepper');
    }

    private function otpHash(string $challengeId, string $otp): string
    {
        return $this->keyedHash($challengeId . '|' . $otp, 'otp_pepper');
    }

    private function privacyHash(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $this->keyedHash($value, 'privacy_pepper');
    }

    private function keyedHash(string $value, string $configKey): string
    {
        $pepper = (string) config('licenses.' . $configKey, config('app.key'));
        if ($pepper === '') {
            throw new RuntimeException('License service peppers are not configured.');
        }
        return hash_hmac('sha256', $value, $pepper);
    }

    private function recordEvent(GameLicense $license, string $eventType, ?string $fromInstall = null, ?string $toInstall = null, string $ip = '', string $userAgent = '', array $metadata = []): void
    {
        LicenseBindingEvent::query()->create([
            'license_id' => $license->id,
            'event_type' => $eventType,
            'from_install_hash' => $fromInstall,
            'to_install_hash' => $toInstall,
            'ip_hash' => $this->privacyHash($ip),
            'user_agent_hash' => $this->privacyHash($userAgent),
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    private function error(string $code, string $message, int $httpStatus, array $extra = []): array
    {
        return array_merge([
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'http_status' => $httpStatus,
        ], $extra);
    }
}
