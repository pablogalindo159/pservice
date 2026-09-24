<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceOrder extends Model
{
    protected $fillable = ['number', 'client_name', 'status'];

    /** URLs usam o número da OS: /os/1020 */
    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /** Aceita o número da OS e, por compatibilidade com links antigos, o id interno. */
    public function resolveRouteBinding($value, $field = null)
    {
        $value = preg_replace('/^os/i', '', (string) $value);

        return $this->where($field ?? 'number', $value)->first()
            ?? (ctype_digit($value) ? $this->find($value) : null);
    }

    /** Âncora de uma etapa na página da OS: OS1020-entrada */
    public function stageAnchor(string $stage): string
    {
        return 'OS'.$this->number.'-'.\Illuminate\Support\Str::slug($stage);
    }

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
