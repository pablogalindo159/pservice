<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime', 'active' => 'boolean'];

    public function canCreateOs(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'laboratory', 'technician'], true);
    }

    public function canTakePhotos(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'technician', 'laboratory'], true);
    }

    public function canDeletePhotos(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'laboratory'], true);
    }

    public function canDownload(): bool
    {
        return in_array($this->role, ['admin', 'manager', 'laboratory'], true);
    }

    public function canAudit(): bool
    {
        return in_array($this->role, ['admin', 'manager'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getRoleLabelAttribute(): string
    {
        return config('pservice.roles')[$this->role] ?? $this->role;
    }
}
