<?php

namespace App\Models;

class GameLicense extends BaseModel
{
    const STATUS_ACTIVE = 'active';
    const STATUS_REVOKED = 'revoked';
    const STATUS_QUARANTINED = 'quarantined';

    protected $table = 'game_licenses';

    protected $casts = [
        'is_legacy' => 'boolean',
        'requires_email_verification' => 'boolean',
        'binding_version' => 'integer',
        'claimed_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'recovery_override_until' => 'datetime',
    ];

    protected $fillable = [
        'carmis_id',
        'order_id',
        'sku_id',
        'code_hash',
        'game_id',
        'device_token_hash',
        'install_id_hash',
        'status',
        'is_legacy',
        'requires_email_verification',
        'binding_version',
        'claimed_at',
        'last_verified_at',
        'recovery_override_until',
    ];

    public function carmis()
    {
        return $this->belongsTo(Carmis::class, 'carmis_id')->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id')->withTrashed();
    }

    public function sku()
    {
        return $this->belongsTo(GoodsSku::class, 'sku_id')->withTrashed();
    }

    public function recoveryChallenges()
    {
        return $this->hasMany(LicenseRecoveryChallenge::class, 'license_id');
    }

    public function bindingEvents()
    {
        return $this->hasMany(LicenseBindingEvent::class, 'license_id');
    }
}
