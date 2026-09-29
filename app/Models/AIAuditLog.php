<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIAuditLog extends Model
{
    protected $casts = ['details' => 'array'];

    protected $table = 'ai_audit_logs';

    protected $fillable = [
        'user_id',
        'event_type',
        'details',
        'ip_address',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
