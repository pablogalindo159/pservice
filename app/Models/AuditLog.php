<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'service_order_id', 'photo_id', 'metadata', 'ip'];

    protected $casts = ['metadata' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function photo()
    {
        return $this->belongsTo(Photo::class, 'photo_id')->withTrashed();
    }

    /** Registra uma ação com usuário e IP da requisição atual. */
    public static function record(string $action, ?int $orderId = null, ?int $photoId = null, array $metadata = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'service_order_id' => $orderId,
            'photo_id' => $photoId,
            'metadata' => $metadata ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    public function getActionLabelAttribute(): string
    {
        return ([
            'auth.login' => 'Login',
            'auth.logout' => 'Logout',
            'os.created' => 'OS criada',
            'os.status_changed' => 'Status alterado',
            'photo.added' => 'Foto adicionada',
            'photo.deleted' => 'Foto excluída',
            'photo.download' => 'Download',
            'user.created' => 'Usuário criado',
            'user.updated' => 'Usuário alterado',
            'user.toggled' => 'Usuário ativado/desativado',
            'user.password_reset' => 'Senha redefinida',
            'geo.check' => ($this->metadata['dentro_da_area'] ?? false) ? 'Localização: dentro da área' : 'Localização: fora da área',
            'settings.geo' => 'Área da empresa alterada',
        ][$this->action] ?? $this->action).(($this->metadata['auto'] ?? false) ? ' (automático)' : '');
    }
}
