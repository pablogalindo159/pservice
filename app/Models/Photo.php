<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Photo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'service_order_id', 'user_id', 'stage', 'original_path', 'preview_path',
        'thumbnail_path', 'original_name', 'mime_type', 'size', 'captured_at',
        'geo_lat', 'geo_lng', 'geo_distance',
    ];

    protected $casts = ['captured_at' => 'datetime', 'geo_distance' => 'integer'];

    /** Dados usados pela grade e pelo visualizador (a tela e o upload usam o mesmo formato). */
    public function toViewerArray(bool $canDelete = false): array
    {
        $seq = preg_match('/_(\d+)\.[a-z0-9]+$/i', (string) $this->original_path, $m) ? $m[1] : null;
        $time = $this->captured_at?->format('H:i');

        return [
            'id' => $this->id,
            'thumb' => route('photos.file', [$this, 'size' => 'thumb']),
            'view' => route('photos.file', [$this, 'size' => 'preview']),
            'original' => route('photos.file', $this),
            'caption' => $this->stage.' · '.($this->user?->name ?? '—').' · '.$this->captured_at?->format('d/m/Y H:i')
                .($this->geo_distance !== null ? ' · 📍 a '.\App\Support\Geo::formatDistance($this->geo_distance).' da empresa' : ''),
            'label' => $seq ? "{$seq} · {$time}" : (string) $time,
            'delete' => $canDelete ? route('photos.destroy', $this) : null,
        ];
    }

    public function order()
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
