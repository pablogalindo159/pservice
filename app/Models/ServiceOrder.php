<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    protected $fillable = ['number', 'client_name', 'status'];

    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    public function audits()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return config('pservice.statuses')[$this->status] ?? $this->status;
    }
}
