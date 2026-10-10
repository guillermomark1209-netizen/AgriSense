<?php

namespace App\Models;

use App\Mail\AuthMessage;
use App\Services\AuthMailService;
use App\Services\EmailVerificationService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $attributes = ['role' => 'user'];

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function sendEmailVerificationNotification(): void
    {
        app(EmailVerificationService::class)->send($this);
    }

    public function sendPasswordResetNotification(mixed $token): void
    {
        app(AuthMailService::class)->send(new AuthMessage('reset', $this->id, $this->email, $this->name, $token));
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function crops(): HasMany
    {
        return $this->hasMany(Crop::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function accessibleDevices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'device_user_access')->withPivot('is_selected')->withTimestamps();
    }

    public function availableDevices(): Builder
    {
        return Device::query()->when(! $this->isAdmin(), fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('devices.user_id', $this->id)
            ->orWhereHas('authorizedUsers', fn (Builder $query) => $query->where('users.id', $this->id))));
    }

    public function monitoringDevice(): ?Device
    {
        $selectedDeviceId = $this->selectedDeviceAccess()->value('device_id');
        if ($selectedDeviceId) {
            return $this->availableDevices()->with('crop')->find($selectedDeviceId);
        }

        $devices = $this->availableDevices()->where('is_active', true)->with('crop');

        return (clone $devices)->whereHas('readings')->withMax('readings', 'reading_at')->orderByDesc('readings_max_reading_at')->first()
            ?? $devices->orderBy('devices.id')->first();
    }

    public function selectedDeviceAccess(): HasOne
    {
        return $this->hasOne(DeviceUserAccess::class)->where('is_selected', true)->with('device');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AIConversation::class);
    }

    public function aiAuditLogs(): HasMany
    {
        return $this->hasMany(AIAuditLog::class);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, ['admin', 'user'], true) && $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
