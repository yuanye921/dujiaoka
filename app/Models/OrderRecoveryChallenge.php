<?php

namespace App\Models;

class OrderRecoveryChallenge extends BaseModel
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'order_recovery_challenges';

    protected $casts = [
        'has_orders' => 'boolean',
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $fillable = [
        'id',
        'email_hash',
        'email_cipher',
        'otp_hash',
        'otp_cipher',
        'requested_ip_hash',
        'user_agent_hash',
        'has_orders',
        'attempts',
        'expires_at',
        'used_at',
    ];
}
