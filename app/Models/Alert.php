<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Alertas para Admin e Gerente (sino no menu). */
class Alert extends Model
{
    protected $fillable = ['user_id', 'service_order_id', 'type', 'message', 'meta', 'seen_at', 'seen_by'];

    protected $casts = ['meta' => 'array', 'seen_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function seenBy()
    {
        return $this->belongsTo(User::class, 'seen_by');
    }

    public function order()
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public static function unseenCount(): int
    {
        return static::whereNull('seen_at')->count();
    }
}
