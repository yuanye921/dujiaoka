<?php

namespace App\Models;

class LicenseBindingEvent extends BaseModel
{
    const UPDATED_AT = null;

    protected $table = 'license_binding_events';

    protected $fillable = [
        'license_id',
        'event_type',
        'from_install_hash',
        'to_install_hash',
        'ip_hash',
        'user_agent_hash',
        'metadata',
    ];

    public function license()
    {
        return $this->belongsTo(GameLicense::class, 'license_id');
    }
}
