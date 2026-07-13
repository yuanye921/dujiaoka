<?php

namespace App\Models;

class LicenseRecoveryChallenge extends BaseModel
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'license_recovery_challenges';

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $fillable = [
        'id',
        'license_id',
        'otp_hash',
        'otp_cipher',
        'requested_ip_hash',
        'user_agent_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    public function license()
    {
        return $this->belongsTo(GameLicense::class, 'license_id');
    }
}
