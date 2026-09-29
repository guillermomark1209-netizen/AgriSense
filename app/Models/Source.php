<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $table = 'sources';

    protected $fillable = [
        'expires_at',
        'verified_by',
        'verified_at',
        'title',
        'organization',
        'crop',
        'topic',
        'type',
        'verification_status',
        'url',
        'published_at',
        'is_active',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
        'published_at' => 'date',
    ];

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function scopeEligible(Builder $query): void
    {
        $query->where('verification_status', 'verified')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()));
    }
}
