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
    ];

    protected $casts = ['captured_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
